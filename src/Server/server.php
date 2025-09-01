<?php

/**
 * Keep Web UI Server
 * 
 * Lightweight HTTP server for local web UI.
 * Run with: php -S localhost:4000 src/Server/server.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use STS\Keep\KeepManager;
use STS\Keep\Commands\ShowCommand;
use STS\Keep\Commands\SetCommand;
use STS\Keep\Commands\GetCommand;
use STS\Keep\Commands\DeleteCommand;
use STS\Keep\Commands\DiffCommand;
use STS\Keep\Commands\SearchCommand;
use STS\Keep\Commands\VerifyCommand;

// Initialize server - get token from environment or generate one
$AUTH_TOKEN = $_ENV['KEEP_AUTH_TOKEN'] ?? $_SERVER['KEEP_AUTH_TOKEN'] ?? bin2hex(random_bytes(32));
if (!isset($_ENV['KEEP_AUTH_TOKEN'])) {
    error_log("Keep UI Token: " . $AUTH_TOKEN);
}

// Create request from globals
$request = Request::createFromGlobals();
$path = $request->getPathInfo();
$method = $request->getMethod();

// Serve static files
if ($path === '/' || $path === '/index.html') {
    serveFile(__DIR__ . '/public/index.html', 'text/html');
}
if (str_starts_with($path, '/assets/')) {
    $file = __DIR__ . '/public' . $path;
    if (file_exists($file)) {
        $mimeTypes = [
            'js' => 'application/javascript',
            'css' => 'text/css',
            'json' => 'application/json',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
        ];
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        serveFile($file, $mimeTypes[$ext] ?? 'application/octet-stream');
    }
}

// API routes require authentication
if (str_starts_with($path, '/api/')) {
    // Check auth token
    $token = $request->headers->get('X-Auth-Token');
    if ($token !== $AUTH_TOKEN) {
        send(new JsonResponse(['error' => 'Unauthorized'], 401));
    }
    
    // Initialize Keep
    $manager = new KeepManager();
    
    // Route API requests
    try {
        $response = match (true) {
            // Secrets
            $method === 'GET' && $path === '/api/secrets' => listSecrets($request, $manager),
            $method === 'GET' && preg_match('#^/api/secrets/(.+)$#', $path, $m) => getSecret($m[1], $request, $manager),
            $method === 'POST' && $path === '/api/secrets' => createSecret($request, $manager),
            $method === 'PUT' && preg_match('#^/api/secrets/(.+)$#', $path, $m) => updateSecret($m[1], $request, $manager),
            $method === 'DELETE' && preg_match('#^/api/secrets/(.+)$#', $path, $m) => deleteSecret($m[1], $request, $manager),
            
            // Search
            $method === 'GET' && $path === '/api/search' => searchSecrets($request, $manager),
            
            // Vaults & Stages
            $method === 'GET' && $path === '/api/vaults' => listVaults($manager),
            $method === 'GET' && $path === '/api/stages' => listStages($manager),
            $method === 'POST' && $path === '/api/verify' => verifyVaults($request, $manager),
            
            // Diff
            $method === 'GET' && $path === '/api/diff' => getDiff($request, $manager),
            
            // Export
            $method === 'POST' && $path === '/api/export' => exportSecrets($request, $manager),
            
            // 404
            default => new JsonResponse(['error' => 'Not found'], 404)
        };
        
        send($response);
    } catch (Exception $e) {
        send(new JsonResponse([
            'error' => $e->getMessage(),
            'type' => get_class($e)
        ], 500));
    }
}

// Default 404
send(new Response('Not found', 404));

// ============================================================================
// API Handlers - Thin wrappers around Keep commands
// ============================================================================

function listSecrets(Request $request, KeepManager $manager): JsonResponse 
{
    $vault = $request->query->get('vault', $manager->getDefaultVault());
    $stage = $request->query->get('stage', 'local');
    $unmask = $request->query->getBoolean('unmask');
    
    $vaultInstance = $manager->vault($vault);
    $secrets = $vaultInstance->all($stage);
    
    return new JsonResponse([
        'success' => true,
        'data' => $secrets->map(fn($secret) => [
            'key' => $secret->key,
            'value' => $unmask ? $secret->value : $secret->getMaskedValue(),
            'revision' => $secret->revision ?? null,
            'modified' => $secret->modified ?? null,
        ])->values()
    ]);
}

function getSecret(string $key, Request $request, KeepManager $manager): JsonResponse
{
    $vault = $request->query->get('vault', $manager->getDefaultVault());
    $stage = $request->query->get('stage', 'local');
    $unmask = $request->query->getBoolean('unmask');
    
    $vaultInstance = $manager->vault($vault);
    $secret = $vaultInstance->get($key, $stage);
    
    if (!$secret) {
        return new JsonResponse(['error' => 'Secret not found'], 404);
    }
    
    return new JsonResponse([
        'success' => true,
        'data' => [
            'key' => $secret->key,
            'value' => $unmask ? $secret->value : $secret->getMaskedValue(),
            'revision' => $secret->revision ?? null,
            'modified' => $secret->modified ?? null,
        ]
    ]);
}

function createSecret(Request $request, KeepManager $manager): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    
    if (!isset($data['key']) || !isset($data['value'])) {
        return new JsonResponse(['error' => 'Missing key or value'], 400);
    }
    
    $vault = $data['vault'] ?? $manager->getDefaultVault();
    $stage = $data['stage'] ?? 'local';
    
    $vaultInstance = $manager->vault($vault);
    $vaultInstance->put($data['key'], $data['value'], $stage);
    
    return new JsonResponse([
        'success' => true,
        'message' => "Secret '{$data['key']}' created"
    ]);
}

function updateSecret(string $key, Request $request, KeepManager $manager): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    
    if (!isset($data['value'])) {
        return new JsonResponse(['error' => 'Missing value'], 400);
    }
    
    $vault = $data['vault'] ?? $manager->getDefaultVault();
    $stage = $data['stage'] ?? 'local';
    
    $vaultInstance = $manager->vault($vault);
    $vaultInstance->put($key, $data['value'], $stage);
    
    return new JsonResponse([
        'success' => true,
        'message' => "Secret '{$key}' updated"
    ]);
}

function deleteSecret(string $key, Request $request, KeepManager $manager): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    
    $vault = $data['vault'] ?? $manager->getDefaultVault();
    $stage = $data['stage'] ?? 'local';
    
    $vaultInstance = $manager->vault($vault);
    $vaultInstance->forget($key, $stage);
    
    return new JsonResponse([
        'success' => true,
        'message' => "Secret '{$key}' deleted"
    ]);
}

function searchSecrets(Request $request, KeepManager $manager): JsonResponse
{
    $query = $request->query->get('q', '');
    $vault = $request->query->get('vault', $manager->getDefaultVault());
    $stage = $request->query->get('stage', 'local');
    $unmask = $request->query->getBoolean('unmask');
    
    if (empty($query)) {
        return new JsonResponse(['error' => 'Missing search query'], 400);
    }
    
    $vaultInstance = $manager->vault($vault);
    $secrets = $vaultInstance->all($stage);
    
    // Simple search implementation
    $results = $secrets->filter(function($secret) use ($query) {
        return stripos($secret->value, $query) !== false ||
               stripos($secret->key, $query) !== false;
    });
    
    return new JsonResponse([
        'success' => true,
        'data' => $results->map(fn($secret) => [
            'key' => $secret->key,
            'value' => $unmask ? $secret->value : $secret->getMaskedValue(),
            'match' => stripos($secret->value, $query) !== false ? 'value' : 'key'
        ])->values()
    ]);
}

function listVaults(KeepManager $manager): JsonResponse
{
    $vaults = $manager->getAvailableVaults();
    
    return new JsonResponse([
        'success' => true,
        'data' => array_map(fn($name) => [
            'name' => $name,
            'driver' => $manager->getVaultDriver($name),
            'default' => $name === $manager->getDefaultVault()
        ], $vaults)
    ]);
}

function listStages(KeepManager $manager): JsonResponse
{
    // Get stages from settings
    $settings = json_decode(file_get_contents($manager->getSettingsPath()), true);
    $stages = $settings['stages'] ?? ['local', 'staging', 'production'];
    
    return new JsonResponse([
        'success' => true,
        'data' => $stages
    ]);
}

function verifyVaults(Request $request, KeepManager $manager): JsonResponse
{
    $results = [];
    
    foreach ($manager->getAvailableVaults() as $vaultName) {
        try {
            $vault = $manager->vault($vaultName);
            $vault->verify();
            $results[$vaultName] = ['success' => true];
        } catch (Exception $e) {
            $results[$vaultName] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    return new JsonResponse([
        'success' => true,
        'data' => $results
    ]);
}

function getDiff(Request $request, KeepManager $manager): JsonResponse
{
    $stages = $request->query->all()['stages'] ?? ['local', 'staging', 'production'];
    $vaults = $request->query->all()['vaults'] ?? $manager->getAvailableVaults();
    
    $matrix = [];
    
    foreach ($vaults as $vaultName) {
        $vault = $manager->vault($vaultName);
        foreach ($stages as $stage) {
            $secrets = $vault->all($stage);
            foreach ($secrets as $secret) {
                $matrix[$secret->key][$vaultName][$stage] = $secret->getMaskedValue();
            }
        }
    }
    
    return new JsonResponse([
        'success' => true,
        'data' => [
            'matrix' => $matrix,
            'stages' => $stages,
            'vaults' => $vaults
        ]
    ]);
}

function exportSecrets(Request $request, KeepManager $manager): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    
    $vault = $data['vault'] ?? $manager->getDefaultVault();
    $stage = $data['stage'] ?? 'local';
    $format = $data['format'] ?? 'env';
    
    $vaultInstance = $manager->vault($vault);
    $secrets = $vaultInstance->all($stage);
    
    if ($format === 'json') {
        $output = json_encode($secrets->pluck('value', 'key'), JSON_PRETTY_PRINT);
    } else {
        $output = $secrets->map(fn($s) => "{$s->key}=\"{$s->value}\"")->join("\n");
    }
    
    return new JsonResponse([
        'success' => true,
        'data' => [
            'content' => $output,
            'format' => $format,
            'count' => $secrets->count()
        ]
    ]);
}

// ============================================================================
// Helper Functions
// ============================================================================

function serveFile(string $path, string $contentType): void
{
    if (!file_exists($path)) {
        send(new Response('Not found', 404));
    }
    
    $response = new Response(file_get_contents($path));
    $response->headers->set('Content-Type', $contentType);
    $response->headers->set('Cache-Control', 'public, max-age=3600');
    send($response);
}

function send(Response $response): void
{
    $response->send();
    exit;
}
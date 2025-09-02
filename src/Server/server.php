<?php

/**
 * Keep Web UI Server
 * 
 * Lightweight HTTP server for local web UI.
 * Run with: php -S localhost:4000 src/Server/server.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use STS\Keep\Data\Secret;
use STS\Keep\KeepApplication;
use STS\Keep\KeepManager;
use STS\Keep\KeepContainer;
use STS\Keep\Data\Settings;
use STS\Keep\Data\Collections\VaultConfigCollection;

// Initialize server - get token from environment or generate one
$AUTH_TOKEN = $_ENV['KEEP_AUTH_TOKEN'] ?? $_SERVER['KEEP_AUTH_TOKEN'] ?? bin2hex(random_bytes(32));

// Get request details
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$query = [];
parse_str($_SERVER['QUERY_STRING'] ?? '', $query);

// Serve index with embedded auth token
if ($path === '/' || $path === '/index.html') {
    serveIndexWithToken(__DIR__ . '/public/index.html', $AUTH_TOKEN);
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
    $headers = getallheaders();
    $token = $headers['X-Auth-Token'] ?? $headers['x-auth-token'] ?? '';
    
    if ($token !== $AUTH_TOKEN) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
    
    // Get JSON body if present
    $body = [];
    if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $input = file_get_contents('php://input');
        if ($input) {
            $body = json_decode($input, true) ?? [];
        }
    }
    
    // Initialize Keep with proper settings and register in container
    $container = KeepContainer::getInstance();
    $manager = new KeepManager(Settings::load(), VaultConfigCollection::load());
    $container->instance(KeepManager::class, $manager);
    
    // Route API requests
    try {
        // Parse path for parameters
        $response = match (true) {
            // Secrets
            $method === 'GET' && $path === '/api/secrets' => listSecrets($query, $manager),
            $method === 'GET' && preg_match('#^/api/secrets/(.+)$#', $path, $m) => getSecret($m[1], $query, $manager),
            $method === 'POST' && $path === '/api/secrets' => createSecret($body, $manager),
            $method === 'PUT' && preg_match('#^/api/secrets/(.+)$#', $path, $m) => updateSecret($m[1], $body, $manager),
            $method === 'DELETE' && preg_match('#^/api/secrets/(.+)$#', $path, $m) => deleteSecret($m[1], $body, $manager),
            
            // Search
            $method === 'GET' && $path === '/api/search' => searchSecrets($query, $manager),
            
            // Settings & Config
            $method === 'GET' && $path === '/api/settings' => getSettings($manager),
            
            // Vaults & Stages
            $method === 'GET' && $path === '/api/vaults' => listVaults($manager),
            $method === 'GET' && $path === '/api/stages' => listStages($manager),
            $method === 'POST' && $path === '/api/verify' => verifyVaults($body, $manager),
            
            // Diff
            $method === 'GET' && $path === '/api/diff' => getDiff($query, $manager),
            
            // Export
            $method === 'POST' && $path === '/api/export' => exportSecrets($body, $manager),
            
            // 404
            default => ['error' => 'Not found', '_status' => 404]
        };
        
        $status = $response['_status'] ?? 200;
        unset($response['_status']);
        jsonResponse($response, $status);
        
    } catch (Exception $e) {
        jsonResponse([
            'error' => $e->getMessage(),
            'type' => get_class($e)
        ], 500);
    }
}

// Default 404
http_response_code(404);
echo "Not found";
exit;

// ============================================================================
// API Handlers - Thin wrappers around Keep commands
// ============================================================================

function listSecrets(array $query, KeepManager $manager): array 
{
    $vault = $query['vault'] ?? $manager->getDefaultVault();
    $stage = $query['stage'] ?? 'local';
    $unmask = isset($query['unmask']) && $query['unmask'] === 'true';
    
    try {
        $vaultInstance = $manager->vault($vault, $stage);
        $secrets = $vaultInstance->list();
        
        return [
            'secrets' => $secrets->map(fn(Secret $secret) => [
                'key' => $secret->key(),
                'value' => $unmask ? $secret->value() : $secret->masked(),
                'revision' => $secret->revision() ?? null,
                'modified' => null,
            ])->values()->toArray()
        ];
    } catch (Exception $e) {
        // Return empty array if vault is not accessible
        return [
            'secrets' => [],
            'error' => 'Could not access vault: ' . $e->getMessage()
        ];
    }
}

function getSecret(string $key, array $query, KeepManager $manager): array
{
    $vault = $query['vault'] ?? $manager->getDefaultVault();
    $stage = $query['stage'] ?? 'local';
    $unmask = isset($query['unmask']) && $query['unmask'] === 'true';
    
    $vaultInstance = $manager->vault($vault, $stage);
    $secret = $vaultInstance->get(urldecode($key));
    
    if (!$secret) {
        return ['error' => 'Secret not found', '_status' => 404];
    }
    
    return [
        'secret' => [
            'key' => $secret->key(),
            'value' => $unmask ? $secret->value() : $secret->masked(),
            'revision' => $secret->revision() ?? null,
            'modified' => null, // Secret doesn't track modification time
        ]
    ];
}

function createSecret(array $data, KeepManager $manager): array
{
    if (!isset($data['key']) || !isset($data['value'])) {
        return ['error' => 'Missing key or value', '_status' => 400];
    }
    
    $vault = $data['vault'] ?? $manager->getDefaultVault();
    $stage = $data['stage'] ?? 'local';
    
    $vaultInstance = $manager->vault($vault, $stage);
    $vaultInstance->set($data['key'], $data['value']);
    
    return [
        'success' => true,
        'message' => "Secret '{$data['key']}' created"
    ];
}

function updateSecret(string $key, array $data, KeepManager $manager): array
{
    if (!isset($data['value'])) {
        return ['error' => 'Missing value', '_status' => 400];
    }
    
    $vault = $data['vault'] ?? $manager->getDefaultVault();
    $stage = $data['stage'] ?? 'local';
    
    $vaultInstance = $manager->vault($vault, $stage);
    $vaultInstance->set(urldecode($key), $data['value']);
    
    return [
        'success' => true,
        'message' => "Secret '{$key}' updated"
    ];
}

function deleteSecret(string $key, array $data, KeepManager $manager): array
{
    $vault = $data['vault'] ?? $manager->getDefaultVault();
    $stage = $data['stage'] ?? 'local';
    
    $vaultInstance = $manager->vault($vault, $stage);
    $vaultInstance->delete(urldecode($key));
    
    return [
        'success' => true,
        'message' => "Secret '{$key}' deleted"
    ];
}

function searchSecrets(array $query, KeepManager $manager): array
{
    $q = $query['q'] ?? '';
    $vault = $query['vault'] ?? $manager->getDefaultVault();
    $stage = $query['stage'] ?? 'local';
    $unmask = isset($query['unmask']) && $query['unmask'] === 'true';
    
    if (empty($q)) {
        return ['error' => 'Missing search query', '_status' => 400];
    }
    
    $vaultInstance = $manager->vault($vault, $stage);
    $secrets = $vaultInstance->list();
    
    // Simple search implementation
    $results = $secrets->filter(function($secret) use ($q) {
        return stripos($secret->value(), $q) !== false ||
               stripos($secret->key(), $q) !== false;
    });
    
    return [
        'secrets' => $results->map(fn($secret) => [
            'key' => $secret->key(),
            'value' => $unmask ? $secret->value() : $secret->masked(),
            'match' => stripos($secret->value(), $q) !== false ? 'value' : 'key'
        ])->values()->toArray()
    ];
}

function getSettings(KeepManager $manager): array
{
    $settings = $manager->getSettings();
    
    return [
        'app_name' => $settings['app_name'] ?? 'Keep',
        'stages' => $settings['stages'] ?? ['local', 'staging', 'production'],
        'default_vault' => $manager->getDefaultVault(),
        'keep_version' => KeepApplication::VERSION
    ];
}

function listVaults(KeepManager $manager): array
{
    $vaults = $manager->getConfiguredVaults();
    
    $vaultList = $vaults->map(function($config) use ($manager) {
        $slug = $config->slug();
        $name = $config->name();
        $driver = $config->driver();
        
        // Get the vault class to access its friendly NAME constant if available
        $vaultClass = null;
        foreach ($manager->getAvailableVaults() as $class) {
            if ($class::DRIVER === $driver) {
                $vaultClass = $class;
                break;
            }
        }
        
        // Use the name from config, or fall back to the class NAME constant
        $friendlyName = $name ?: ($vaultClass ? $vaultClass::NAME : ucfirst($driver));
        
        return [
            'name' => $slug,  // This is what we'll use as the value
            'display' => $friendlyName . ' (' . $slug . ')'  // This is what we'll show
        ];
    });
    
    return [
        'vaults' => $vaultList->values()->toArray()
    ];
}

function listStages(KeepManager $manager): array
{
    $settings = $manager->getSettings();
    $stages = $settings['stages'] ?? ['local', 'staging', 'production'];
    
    return [
        'stages' => $stages
    ];
}

function verifyVaults(array $data, KeepManager $manager): array
{
    $results = [];
    
    foreach ($manager->getConfiguredVaults() as $vaultConfig) {
        $vaultName = $vaultConfig->slug();
        try {
            $vault = $manager->vault($vaultName, 'local');
            // Simple verification - just try to list
            $vault->list();
            $results[$vaultName] = ['success' => true];
        } catch (Exception $e) {
            $results[$vaultName] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    return [
        'results' => $results
    ];
}

function getDiff(array $query, KeepManager $manager): array
{
    $stages = isset($query['stages']) ? explode(',', $query['stages']) : ['local', 'staging', 'production'];
    $vaults = isset($query['vaults']) ? explode(',', $query['vaults']) : $manager->getAvailableVaults();
    
    $matrix = [];
    
    foreach ($vaults as $vaultName) {
        try {
            foreach ($stages as $stage) {
                $vault = $manager->vault($vaultName, $stage);
                $secrets = $vault->list();
                foreach ($secrets as $secret) {
                    $matrix[$secret->key()][$vaultName][$stage] = $secret->masked();
                }
            }
        } catch (Exception $e) {
            // Skip vault if it fails
        }
    }
    
    return [
        'diff' => $matrix,
        'stages' => $stages,
        'vaults' => $vaults
    ];
}

function exportSecrets(array $data, KeepManager $manager): array
{
    $vault = $data['vault'] ?? $manager->getDefaultVault();
    $stage = $data['stage'] ?? 'local';
    $format = $data['format'] ?? 'env';
    
    $vaultInstance = $manager->vault($vault, $stage);
    $secrets = $vaultInstance->list();
    
    if ($format === 'json') {
        $output = json_encode(
            $secrets->mapWithKeys(fn($s) => [$s->key() => $s->value()])->toArray(),
            JSON_PRETTY_PRINT
        );
    } else {
        $output = $secrets->map(fn($s) => "{$s->key()}=\"{$s->value()}\"")->join("\n");
    }
    
    return [
        'content' => $output,
        'format' => $format,
        'count' => $secrets->count()
    ];
}

// ============================================================================
// Helper Functions
// ============================================================================

function serveFile(string $path, string $contentType): void
{
    if (!file_exists($path)) {
        http_response_code(404);
        echo "Not found";
        exit;
    }
    
    header('Content-Type: ' . $contentType);
    header('Cache-Control: public, max-age=3600');
    readfile($path);
    exit;
}

function serveIndexWithToken(string $path, string $token): void
{
    if (!file_exists($path)) {
        http_response_code(404);
        echo "Not found";
        exit;
    }
    
    $html = file_get_contents($path);
    // Inject the token as a JavaScript variable
    $injection = "<script>window.KEEP_AUTH_TOKEN = '" . htmlspecialchars($token) . "';</script>";
    $html = str_replace('</head>', $injection . "\n</head>", $html);
    
    header('Content-Type: text/html');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $html;
    exit;
}

function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
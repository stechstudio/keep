<?php

/**
 * Keep Web UI Server
 * 
 * Lightweight HTTP server for local web UI.
 * Run with: php -S localhost:4000 src/Server/server.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use STS\Keep\KeepManager;

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
    
    // Initialize Keep
    $manager = new KeepManager();
    
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
    
    $vaultInstance = $manager->vault($vault);
    $secrets = $vaultInstance->all($stage);
    
    return [
        'success' => true,
        'data' => $secrets->map(fn($secret) => [
            'key' => $secret->key,
            'value' => $unmask ? $secret->value : $secret->getMaskedValue(),
            'revision' => $secret->revision ?? null,
            'modified' => $secret->modified ?? null,
        ])->values()->toArray()
    ];
}

function getSecret(string $key, array $query, KeepManager $manager): array
{
    $vault = $query['vault'] ?? $manager->getDefaultVault();
    $stage = $query['stage'] ?? 'local';
    $unmask = isset($query['unmask']) && $query['unmask'] === 'true';
    
    $vaultInstance = $manager->vault($vault);
    $secret = $vaultInstance->get(urldecode($key), $stage);
    
    if (!$secret) {
        return ['error' => 'Secret not found', '_status' => 404];
    }
    
    return [
        'success' => true,
        'data' => [
            'key' => $secret->key,
            'value' => $unmask ? $secret->value : $secret->getMaskedValue(),
            'revision' => $secret->revision ?? null,
            'modified' => $secret->modified ?? null,
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
    
    $vaultInstance = $manager->vault($vault);
    $vaultInstance->put($data['key'], $data['value'], $stage);
    
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
    
    $vaultInstance = $manager->vault($vault);
    $vaultInstance->put(urldecode($key), $data['value'], $stage);
    
    return [
        'success' => true,
        'message' => "Secret '{$key}' updated"
    ];
}

function deleteSecret(string $key, array $data, KeepManager $manager): array
{
    $vault = $data['vault'] ?? $manager->getDefaultVault();
    $stage = $data['stage'] ?? 'local';
    
    $vaultInstance = $manager->vault($vault);
    $vaultInstance->forget(urldecode($key), $stage);
    
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
    
    $vaultInstance = $manager->vault($vault);
    $secrets = $vaultInstance->all($stage);
    
    // Simple search implementation
    $results = $secrets->filter(function($secret) use ($q) {
        return stripos($secret->value, $q) !== false ||
               stripos($secret->key, $q) !== false;
    });
    
    return [
        'success' => true,
        'data' => $results->map(fn($secret) => [
            'key' => $secret->key,
            'value' => $unmask ? $secret->value : $secret->getMaskedValue(),
            'match' => stripos($secret->value, $q) !== false ? 'value' : 'key'
        ])->values()->toArray()
    ];
}

function listVaults(KeepManager $manager): array
{
    $vaults = $manager->getAvailableVaults();
    
    return [
        'success' => true,
        'data' => array_map(fn($name) => [
            'name' => $name,
            'driver' => $manager->getVaultDriver($name),
            'default' => $name === $manager->getDefaultVault()
        ], $vaults)
    ];
}

function listStages(KeepManager $manager): array
{
    // Get stages from settings
    $settingsPath = $_SERVER['HOME'] . '/.keep/settings.json';
    if (file_exists($settingsPath)) {
        $settings = json_decode(file_get_contents($settingsPath), true);
        $stages = $settings['stages'] ?? ['local', 'staging', 'production'];
    } else {
        $stages = ['local', 'staging', 'production'];
    }
    
    return [
        'success' => true,
        'data' => $stages
    ];
}

function verifyVaults(array $data, KeepManager $manager): array
{
    $results = [];
    
    foreach ($manager->getAvailableVaults() as $vaultName) {
        try {
            $vault = $manager->vault($vaultName);
            // Simple verification - just try to list
            $vault->all('local');
            $results[$vaultName] = ['success' => true];
        } catch (Exception $e) {
            $results[$vaultName] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    return [
        'success' => true,
        'data' => $results
    ];
}

function getDiff(array $query, KeepManager $manager): array
{
    $stages = isset($query['stages']) ? explode(',', $query['stages']) : ['local', 'staging', 'production'];
    $vaults = isset($query['vaults']) ? explode(',', $query['vaults']) : $manager->getAvailableVaults();
    
    $matrix = [];
    
    foreach ($vaults as $vaultName) {
        try {
            $vault = $manager->vault($vaultName);
            foreach ($stages as $stage) {
                $secrets = $vault->all($stage);
                foreach ($secrets as $secret) {
                    $matrix[$secret->key][$vaultName][$stage] = $secret->getMaskedValue();
                }
            }
        } catch (Exception $e) {
            // Skip vault if it fails
        }
    }
    
    return [
        'success' => true,
        'data' => [
            'matrix' => $matrix,
            'stages' => $stages,
            'vaults' => $vaults
        ]
    ];
}

function exportSecrets(array $data, KeepManager $manager): array
{
    $vault = $data['vault'] ?? $manager->getDefaultVault();
    $stage = $data['stage'] ?? 'local';
    $format = $data['format'] ?? 'env';
    
    $vaultInstance = $manager->vault($vault);
    $secrets = $vaultInstance->all($stage);
    
    if ($format === 'json') {
        $output = json_encode(
            $secrets->mapWithKeys(fn($s) => [$s->key => $s->value])->toArray(),
            JSON_PRETTY_PRINT
        );
    } else {
        $output = $secrets->map(fn($s) => "{$s->key}=\"{$s->value}\"")->join("\n");
    }
    
    return [
        'success' => true,
        'data' => [
            'content' => $output,
            'format' => $format,
            'count' => $secrets->count()
        ]
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
    
    // Debug: Log that we're injecting the token
    error_log("Injecting token into HTML: " . substr($token, 0, 8) . "...");
    
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
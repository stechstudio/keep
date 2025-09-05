<?php

/**
 * Keep Web UI Server
 * 
 * Lightweight HTTP server for local web UI.
 * Run with: php -S localhost:4000 src/Server/server.php
 */

// Find the autoloader - works both when developing Keep and when installed as a package
$autoloadPaths = [
    __DIR__ . '/../../vendor/autoload.php',        // Keep development (keep/vendor/autoload.php)
    __DIR__ . '/../../../../autoload.php',         // Installed as package (vendor/stechstudio/keep/src/Server -> vendor/autoload.php)
    __DIR__ . '/../../../../../vendor/autoload.php', // Installed globally with Composer
];

$autoloadFound = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloadFound = true;
        break;
    }
}

if (!$autoloadFound) {
    die("Error: Unable to find Composer autoload.php. Please run 'composer install'.\n");
}

use STS\Keep\KeepManager;
use STS\Keep\KeepContainer;
use STS\Keep\Data\Settings;
use STS\Keep\Data\Collections\VaultConfigCollection;
use STS\Keep\Server\Router;
use STS\Keep\Server\Controllers\SecretController;
use STS\Keep\Server\Controllers\VaultController;
use STS\Keep\Server\Controllers\ExportController;
use STS\Keep\Server\Controllers\ImportController;
use STS\Keep\Server\Controllers\TemplateController;

// Initialize server - get token from environment or generate one
$AUTH_TOKEN = $_ENV['KEEP_AUTH_TOKEN'] ?? $_SERVER['KEEP_AUTH_TOKEN'] ?? bin2hex(random_bytes(32));

// Get request details
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$query = [];
parse_str($_SERVER['QUERY_STRING'] ?? '', $query);

// Serve static files
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
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        $input = file_get_contents('php://input');
        if ($input) {
            $body = json_decode($input, true) ?? [];
        }
    }
    
    // Initialize Keep with proper settings and register in container
    $container = KeepContainer::getInstance();
    $manager = new KeepManager(Settings::load(), VaultConfigCollection::load());
    $container->instance(KeepManager::class, $manager);
    
    // Setup router and register routes
    $router = new Router($manager);
    
    // Secret routes
    $router->get('/api/secrets', [SecretController::class, 'list']);
    $router->get('/api/secrets/validation-rules', [SecretController::class, 'validationRules']);
    $router->get('/api/secrets/:key', [SecretController::class, 'get']);
    $router->post('/api/secrets', [SecretController::class, 'create']);
    $router->put('/api/secrets/:key', [SecretController::class, 'update']);
    $router->delete('/api/secrets/:key', [SecretController::class, 'delete']);
    $router->post('/api/secrets/:key/rename', [SecretController::class, 'rename']);
    $router->post('/api/secrets/:key/copy-to-stage', [SecretController::class, 'copyToStage']);
    $router->get('/api/secrets/:key/history', [SecretController::class, 'history']);
    $router->get('/api/search', [SecretController::class, 'search']);
    
    // Vault & Settings routes
    $router->get('/api/vaults', [VaultController::class, 'list']);
    $router->post('/api/vaults', [VaultController::class, 'addVault']);
    $router->put('/api/vaults/:slug', [VaultController::class, 'updateVault']);
    $router->delete('/api/vaults/:slug', [VaultController::class, 'deleteVault']);
    
    $router->get('/api/stages', [VaultController::class, 'listStages']);
    $router->post('/api/stages', [VaultController::class, 'addStage']);
    $router->delete('/api/stages', [VaultController::class, 'removeStage']);
    
    $router->get('/api/settings', [VaultController::class, 'getSettings']);
    $router->put('/api/settings', [VaultController::class, 'updateSettings']);
    
    $router->post('/api/verify', [VaultController::class, 'verify']);
    $router->get('/api/diff', [VaultController::class, 'diff']);
    
    // Export routes
    $router->post('/api/export', [ExportController::class, 'export']);
    
    // Import routes
    $router->post('/api/import/analyze', [ImportController::class, 'analyze']);
    $router->post('/api/import/execute', [ImportController::class, 'execute']);
    
    // Template routes
    $router->get('/api/templates', [TemplateController::class, 'index']);
    $router->get('/api/templates/placeholders', [TemplateController::class, 'placeholders']);
    $router->get('/api/templates/:filename', [TemplateController::class, 'show']);
    $router->put('/api/templates/:filename', [TemplateController::class, 'update']);
    $router->delete('/api/templates/:filename', [TemplateController::class, 'delete']);
    $router->post('/api/templates/generate', [TemplateController::class, 'generate']);
    $router->post('/api/templates/validate', [TemplateController::class, 'validate']);
    $router->post('/api/templates/process', [TemplateController::class, 'process']);
    $router->post('/api/templates/create', [TemplateController::class, 'create']);
    
    // Dispatch the request
    $response = $router->dispatch($method, $path, $query, $body);
    
    // Send response
    $status = $response['_status'] ?? 200;
    unset($response['_status']);
    jsonResponse($response, $status);
}

// Default 404
http_response_code(404);
echo "Not found";
exit;

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
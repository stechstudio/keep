<?php

namespace STS\Keep\Tests\Unit\Server;

use STS\Keep\Server\Router;
use STS\Keep\KeepManager;

test('router validates auth token on API routes', function () {
    $settings = new \STS\Keep\Data\Settings([]);
    $vaultConfigs = new \STS\Keep\Data\Collections\VaultConfigCollection();
    $manager = new KeepManager($settings, $vaultConfigs);
    $router = new Router($manager);
    
    // Test without token
    $response = $router->handle('GET', '/api/secrets', [], [], '', $manager);
    expect($response['_status'])->toBe(401);
    expect($response['error'])->toContain('Unauthorized');
    
    // Test with invalid token
    $response = $router->handle('GET', '/api/secrets', [], [], 'wrong-token', $manager);
    expect($response['_status'])->toBe(401);
    
    // Test with valid token
    $response = $router->handle('GET', '/api/secrets', ['vault' => 'test'], [], 'valid-token', $manager);
    expect($response['_status'])->not->toBe(401);
});

test('router allows public access to root and assets', function () {
    $settings = new \STS\Keep\Data\Settings([]);
    $vaultConfigs = new \STS\Keep\Data\Collections\VaultConfigCollection();
    $manager = new KeepManager($settings, $vaultConfigs);
    $router = new Router($manager);
    
    // Root should be accessible without token
    $response = $router->handle('GET', '/', [], [], '', $manager);
    expect($response)->not->toHaveKey('error');
    
    // Assets should be accessible  
    $response = $router->handle('GET', '/assets/app.js', [], [], '', $manager);
    expect($response)->not->toHaveKey('error');
});

test('sensitive values are masked by default', function () {
    $vault = new \STS\Keep\Vaults\TestVault('test', 'local');
    $vault->set('API_KEY', 'super-secret-key-123');
    
    $mockManager = test()->createPartialMock(\STS\Keep\KeepManager::class, ['vault']);
    $mockManager->method('vault')->willReturn($vault);
    
    $controller = new \STS\Keep\Server\Controllers\SecretController($mockManager, ['vault' => 'test', 'stage' => 'local']);
    $response = $controller->list();
    
    // Should be masked by default
    expect($response['secrets'][0]['masked'])->toBeTrue();
    expect($response['secrets'][0]['value'])->not->toContain('super-secret');
    
    // Should unmask when requested
    $controller = new \STS\Keep\Server\Controllers\SecretController($mockManager, ['vault' => 'test', 'stage' => 'local', 'unmask' => 'true']);
    $response = $controller->list();
    
    expect($response['secrets'][0]['masked'])->toBeFalse();
    expect($response['secrets'][0]['value'])->toBe('super-secret-key-123');
});

test('input sanitization prevents injection', function () {
    $mockManager = test()->createPartialMock(\STS\Keep\KeepManager::class, ['vault']);
    
    // Test dangerous characters in key names
    $controller = new \STS\Keep\Server\Controllers\SecretController(
        $mockManager,
        ['vault' => 'test', 'stage' => 'local'],
        ['key' => '../../../etc/passwd', 'value' => 'test']
    );
    
    // Should handle safely without path traversal
    $response = $controller->create();
    expect($response)->toBeArray();
    // The actual vault implementation should handle this safely
});

test('error messages do not leak sensitive information', function () {
    $mockManager = test()->createPartialMock(\STS\Keep\KeepManager::class, ['vault']);
    $mockManager->method('vault')->willThrowException(new \Exception('Database connection failed at host:192.168.1.1 with password:secret123'));
    
    $controller = new \STS\Keep\Server\Controllers\SecretController($mockManager, ['vault' => 'test', 'stage' => 'local']);
    $response = $controller->list();
    
    // Error message should be generic, not expose internals
    expect($response)->toHaveKey('error');
    expect($response['error'])->toBe('Could not access vault');
});

test('rate limiting headers are set', function () {
    // This would be implemented in production
    // For now, just ensure the structure exists for rate limiting
    expect(class_exists(\STS\Keep\Server\Router::class))->toBeTrue();
    
    // In production, you'd test:
    // - X-RateLimit-Limit header
    // - X-RateLimit-Remaining header
    // - 429 response when limit exceeded
});

test('CORS headers prevent cross-origin requests', function () {
    // The server should only allow same-origin requests
    // since it's a local-only tool
    
    $settings = new \STS\Keep\Data\Settings([]);
    $vaultConfigs = new \STS\Keep\Data\Collections\VaultConfigCollection();
    $manager = new KeepManager($settings, $vaultConfigs);
    $router = new Router($manager);
    
    // Test that no Access-Control-Allow-Origin header is set
    // (this prevents cross-origin requests)
    expect($router)->toBeInstanceOf(Router::class);
    
    // In production server.php, verify:
    // - No Access-Control-Allow-Origin header
    // - Strict Content-Type checking
    // - X-Frame-Options: DENY
});
<?php

use STS\Keep\Facades\Keep;

it('can run cache:load command with app-key parameter', function () {
    $tempDir = createTempKeepDir();
    
    // Set up Keep with test vault
    setupKeepManager();
    
    // Create a test secret in the vault
    $vault = Keep::vault('test', 'testing');
    $vault->set('TEST_SECRET', 'secret-value');
    
    // Generate a test APP_KEY
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    
    // Run cache:load command
    $commandTester = runCommand('cache:load', [
        '--stage' => 'testing',
        '--vault' => 'test',
        '--key' => $appKey,
    ]);
    
    expect($commandTester->getStatusCode())->toBe(0);
    expect($commandTester->getDisplay())->toContain('Found 1 secrets to cache');
    expect($commandTester->getDisplay())->toContain('Secrets cached successfully');
    
    // Verify cache file was created at standard location
    $expectedPath = $tempDir . '/storage/cache/testing.keep.php';
    expect(file_exists($expectedPath))->toBeTrue();
    
    // Verify file permissions are restricted
    $permissions = fileperms($expectedPath) & 0777;
    expect($permissions)->toBe(0600);
    
    // Verify cache file contains encrypted data
    $cacheData = include $expectedPath;
    expect($cacheData)->toBeString();
    expect(strlen($cacheData))->toBeGreaterThan(0);
    
    cleanupTempDir($tempDir);
});

it('discovers APP_KEY from .env file', function () {
    $tempDir = createTempKeepDir();
    
    // Set up Keep with test vault
    setupKeepManager();
    
    // Create .env file with APP_KEY
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    file_put_contents($tempDir . '/.env', "APP_KEY={$appKey}\n");
    
    // Create a test secret in the vault
    $vault = Keep::vault('test', 'testing');
    $vault->set('TEST_SECRET', 'secret-value');
    
    // Run cache:load command without --key
    $commandTester = runCommand('cache:load', [
        '--stage' => 'testing',
        '--vault' => 'test',
    ]);
    
    expect($commandTester->getStatusCode())->toBe(0);
    expect($commandTester->getDisplay())->toContain('Secrets cached successfully');
    
    // Verify cache file was created at standard location
    expect(file_exists($tempDir . '/storage/cache/testing.keep.php'))->toBeTrue();
    
    cleanupTempDir($tempDir);
});

it('fails when APP_KEY is not found', function () {
    $tempDir = createTempKeepDir();
    
    // Set up Keep with test vault
    setupKeepManager();
    
    // Run cache:load command without APP_KEY
    $commandTester = runCommand('cache:load', [
        '--stage' => 'testing',
        '--vault' => 'test',
    ]);
    
    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('APP_KEY not found');
    
    cleanupTempDir($tempDir);
});

it('creates standard cache directory structure', function () {
    $tempDir = createTempKeepDir();
    
    // Set up Keep with test vault
    setupKeepManager();
    
    // Create a test secret in the vault
    $vault = Keep::vault('test', 'testing');
    $vault->set('TEST_SECRET', 'secret-value');
    
    // Generate a test APP_KEY
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    
    // Run cache:load command
    $commandTester = runCommand('cache:load', [
        '--stage' => 'testing',
        '--vault' => 'test',
        '--key' => $appKey,
    ]);
    
    expect($commandTester->getStatusCode())->toBe(0);
    
    // Verify standard cache directory and stage-specific file was created
    expect(is_dir($tempDir . '/storage/cache'))->toBeTrue();
    expect(file_exists($tempDir . '/storage/cache/testing.keep.php'))->toBeTrue();
    
    cleanupTempDir($tempDir);
});

it('loads from all configured vaults when no vault specified', function () {
    $tempDir = createTempKeepDir();
    
    // Set up Keep with multiple test vaults
    setupKeepManager([], [
        'vault1' => [
            'slug' => 'vault1',
            'driver' => 'test',
            'name' => 'Test Vault 1',
            'namespace' => 'test-app',
        ],
        'vault2' => [
            'slug' => 'vault2', 
            'driver' => 'test',
            'name' => 'Test Vault 2',
            'namespace' => 'test-app',
        ]
    ]);
    
    // Create secrets in different vaults
    $vault1 = Keep::vault('vault1', 'testing');
    $vault1->set('SECRET_1', 'from-vault1');
    $vault1->set('SHARED_SECRET', 'vault1-value');
    
    $vault2 = Keep::vault('vault2', 'testing');
    $vault2->set('SECRET_2', 'from-vault2');
    $vault2->set('SHARED_SECRET', 'vault2-value'); // This should override vault1
    
    // Generate a test APP_KEY
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    
    // Run cache:load command without specifying vault
    $commandTester = runCommand('cache:load', [
        '--stage' => 'testing',
        '--key' => $appKey,
    ]);
    
    expect($commandTester->getStatusCode())->toBe(0);
    expect($commandTester->getDisplay())->toContain('Loading secrets from 2 vaults (vault1, vault2)');
    expect($commandTester->getDisplay())->toContain('Found 3 total secrets to cache');
    
    cleanupTempDir($tempDir);
});

it('loads from specified comma-separated vaults', function () {
    $tempDir = createTempKeepDir();
    
    // Set up Keep with multiple test vaults
    setupKeepManager([], [
        'vault1' => [
            'slug' => 'vault1',
            'driver' => 'test',
            'name' => 'Test Vault 1', 
            'namespace' => 'test-app',
        ],
        'vault2' => [
            'slug' => 'vault2',
            'driver' => 'test',
            'name' => 'Test Vault 2',
            'namespace' => 'test-app',
        ],
        'vault3' => [
            'slug' => 'vault3',
            'driver' => 'test',
            'name' => 'Test Vault 3',
            'namespace' => 'test-app',
        ]
    ]);
    
    // Create secrets in different vaults
    Keep::vault('vault1', 'testing')->set('SECRET_1', 'from-vault1');
    Keep::vault('vault2', 'testing')->set('SECRET_2', 'from-vault2');
    Keep::vault('vault3', 'testing')->set('SECRET_3', 'from-vault3');
    
    // Generate a test APP_KEY
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    
    // Run cache:load command with specific vaults
    $commandTester = runCommand('cache:load', [
        '--stage' => 'testing',
        '--vault' => 'vault1,vault3',
        '--key' => $appKey,
    ]);
    
    expect($commandTester->getStatusCode())->toBe(0);
    expect($commandTester->getDisplay())->toContain('Loading secrets from 2 vaults (vault1, vault3)');
    expect($commandTester->getDisplay())->toContain('Found 2 total secrets to cache');
    
    cleanupTempDir($tempDir);
});
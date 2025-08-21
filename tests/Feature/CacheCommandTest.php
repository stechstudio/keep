<?php

use STS\Keep\Facades\Keep;

beforeEach(function () {
    $this->tempDir = createTempKeepDir();
    
    // Create .keep directory and settings to initialize Keep
    mkdir('.keep');
    mkdir('.keep/vaults');

    $settings = [
        'app_name' => 'test-app',
        'namespace' => 'test-app',
        'default_vault' => 'test',
        'stages' => ['testing', 'production'],
        'created_at' => date('c'),
        'version' => '1.0',
    ];

    file_put_contents('.keep/settings.json', json_encode($settings, JSON_PRETTY_PRINT));

    // Create test vault configuration for testing (never hits AWS)
    $vaultConfig = [
        'driver' => 'test',
        'name' => 'Test Vault',
        'namespace' => 'test-app',
    ];

    file_put_contents('.keep/vaults/test.json', json_encode($vaultConfig, JSON_PRETTY_PRINT));
    
    // Create additional vault configurations for multi-vault tests
    $vault1Config = [
        'driver' => 'test',
        'name' => 'Test Vault 1',
        'namespace' => 'test-app',
    ];
    $vault2Config = [
        'driver' => 'test',
        'name' => 'Test Vault 2',
        'namespace' => 'test-app',
    ];

    file_put_contents('.keep/vaults/vault1.json', json_encode($vault1Config, JSON_PRETTY_PRINT));
    file_put_contents('.keep/vaults/vault2.json', json_encode($vault2Config, JSON_PRETTY_PRINT));
    
    // Helper function to run commands without clearing storage between commands
    $this->runCommandWithPersistence = function(string $commandName, array $input = []) {
        static $app = null;
        
        if ($app === null) {
            $app = createKeepApp();
            STS\Keep\Facades\Keep::addVaultDriver(STS\Keep\Tests\Support\TestVault::class);
            STS\Keep\Tests\Support\TestVault::clearAll();
        }
        
        $command = $app->find($commandName);
        $commandTester = new Symfony\Component\Console\Tester\CommandTester($command);
        $input['--no-interaction'] = true;
        $commandTester->execute($input);
        return $commandTester;
    };
});

afterEach(function () {
    if (isset($this->tempDir)) {
        cleanupTempDir($this->tempDir);
    }
});

it('can run cache:load command with app-key parameter', function () {
    // Create a test secret first
    $setTester = ($this->runCommandWithPersistence)('set', [
        'key' => 'TEST_SECRET',
        'value' => 'secret-value',
        '--stage' => 'testing',
        '--vault' => 'test',
        '--plain' => true,
    ]);
    
    expect($setTester->getStatusCode())->toBe(0);
    
    // Generate a test APP_KEY
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    
    // Run cache:load command (using same app instance so storage persists)
    $commandTester = ($this->runCommandWithPersistence)('cache:load', [
        '--stage' => 'testing',
        '--vault' => 'test',
        '--key' => $appKey,
    ]);
    
    expect($commandTester->getStatusCode())->toBe(0);
    expect($commandTester->getDisplay())->toContain('Found 1 total secrets to cache');
    expect($commandTester->getDisplay())->toContain('Secrets cached successfully');
    
    // Verify cache file was created at standard location
    $expectedPath = $this->tempDir . '/storage/cache/testing.keep.php';
    expect(file_exists($expectedPath))->toBeTrue();
    
    // Verify file permissions are restricted
    $permissions = fileperms($expectedPath) & 0777;
    expect($permissions)->toBe(0600);
    
    // Verify cache file contains encrypted data
    $cacheData = include $expectedPath;
    expect($cacheData)->toBeString();
    expect(strlen($cacheData))->toBeGreaterThan(0);
});

it('discovers APP_KEY from .env file', function () {
    
    // Create .env file with APP_KEY
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    file_put_contents($this->tempDir . '/.env', "APP_KEY={$appKey}\n");
    
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
    expect(file_exists($this->tempDir . '/storage/cache/testing.keep.php'))->toBeTrue();
});

it('fails when APP_KEY is not found', function () {
    
    // Run cache:load command without APP_KEY
    $commandTester = runCommand('cache:load', [
        '--stage' => 'testing',
        '--vault' => 'test',
    ]);
    
    expect($commandTester->getStatusCode())->toBe(1);
    expect($commandTester->getDisplay())->toContain('APP_KEY not found');
    
});

it('creates standard cache directory structure', function () {
    
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
    expect(is_dir($this->tempDir . '/storage/cache'))->toBeTrue();
    expect(file_exists($this->tempDir . '/storage/cache/testing.keep.php'))->toBeTrue();
});

it('loads from all configured vaults when no vault specified', function () {
    // Create secrets in different vaults using commands
    ($this->runCommandWithPersistence)('set', [
        'key' => 'SECRET_1',
        'value' => 'from-vault1',
        '--stage' => 'testing',
        '--vault' => 'vault1',
        '--plain' => true,
    ]);
    
    ($this->runCommandWithPersistence)('set', [
        'key' => 'SECRET_1B',
        'value' => 'vault1-value-b',
        '--stage' => 'testing',
        '--vault' => 'vault1',
        '--plain' => true,
    ]);
    
    ($this->runCommandWithPersistence)('set', [
        'key' => 'SECRET_2',
        'value' => 'from-vault2',
        '--stage' => 'testing',
        '--vault' => 'vault2',
        '--plain' => true,
    ]);
    
    // Generate a test APP_KEY
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    
    
    // Run cache:load command without specifying vault
    $commandTester = ($this->runCommandWithPersistence)('cache:load', [
        '--stage' => 'testing',
        '--key' => $appKey,
    ]);
    
    expect($commandTester->getStatusCode())->toBe(0);
    expect($commandTester->getDisplay())->toContain('Loading secrets from 3 vaults (test, vault1, vault2)');
    expect($commandTester->getDisplay())->toContain('Found 1 total secrets to cache');
    
});

it('loads from specified comma-separated vaults', function () {
    // Create vault3 configuration (vault1 and vault2 already exist from beforeEach)
    $vault3Config = [
        'driver' => 'test',
        'name' => 'Test Vault 3',
        'namespace' => 'test-app',
    ];

    file_put_contents('.keep/vaults/vault3.json', json_encode($vault3Config, JSON_PRETTY_PRINT));
    
    // Create secrets in different vaults using commands
    ($this->runCommandWithPersistence)('set', [
        'key' => 'SECRET_1',
        'value' => 'from-vault1',
        '--stage' => 'testing',
        '--vault' => 'vault1',
        '--plain' => true,
    ]);
    
    ($this->runCommandWithPersistence)('set', [
        'key' => 'SECRET_2',
        'value' => 'from-vault2',
        '--stage' => 'testing',
        '--vault' => 'vault2',
        '--plain' => true,
    ]);
    
    ($this->runCommandWithPersistence)('set', [
        'key' => 'SECRET_3',
        'value' => 'from-vault3',
        '--stage' => 'testing',
        '--vault' => 'vault3',
        '--plain' => true,
    ]);
    
    // Generate a test APP_KEY
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    
    // Run cache:load command with specific vaults
    $commandTester = ($this->runCommandWithPersistence)('cache:load', [
        '--stage' => 'testing',
        '--vault' => 'vault1,vault3',
        '--key' => $appKey,
    ]);
    
    expect($commandTester->getStatusCode())->toBe(0);
    expect($commandTester->getDisplay())->toContain('Loading secrets from 2 vaults (vault1, vault3)');
    expect($commandTester->getDisplay())->toContain('Found 1 total secrets to cache');
    
});
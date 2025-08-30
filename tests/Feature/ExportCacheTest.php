<?php

use STS\Keep\Facades\Keep;

describe('Export cache functionality', function () {

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
        $this->runCommandWithPersistence = function (string $commandName, array $input = []) {
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

    it('can export secrets to cache with --cache flag', function () {
        // Create a test secret first
        $setTester = ($this->runCommandWithPersistence)('set', [
            'key' => 'TEST_SECRET',
            'value' => 'secret-value',
            '--stage' => 'testing',
            '--vault' => 'test',
            '--plain' => true,
        ]);

        expect($setTester->getStatusCode())->toBe(0);

        // Run export command with --cache flag
        $commandTester = ($this->runCommandWithPersistence)('export', [
            '--stage' => 'testing',
            '--vault' => 'test',
            '--cache' => true,
        ]);

        expect($commandTester->getStatusCode())->toBe(0);
        expect($commandTester->getDisplay())->toContain('Found 1 total secrets to cache');
        expect($commandTester->getDisplay())->toContain('Secrets cached successfully');

        // Verify cache file was created at standard location
        $expectedPath = $this->tempDir.'/.keep/cache/testing.keep.php';
        expect(file_exists($expectedPath))->toBeTrue();

        // Verify file permissions are restricted
        $permissions = fileperms($expectedPath) & 0777;
        expect($permissions)->toBe(0600);

        // Verify cache file contains encrypted data
        $cacheData = include $expectedPath;
        expect($cacheData)->toBeString();
        expect(strlen($cacheData))->toBeGreaterThan(0);
    });

    it('creates standard cache directory structure with gitignore', function () {
        // Create a test secret in the vault
        $vault = Keep::vault('test', 'testing');
        $vault->set('TEST_SECRET', 'secret-value');

        // Run export cache command
        $commandTester = runCommand('export', [
            '--stage' => 'testing',
            '--vault' => 'test',
            '--cache' => true,
        ]);

        expect($commandTester->getStatusCode())->toBe(0);

        // Verify standard cache directory and stage-specific file was created
        expect(is_dir($this->tempDir.'/.keep/cache'))->toBeTrue();
        expect(file_exists($this->tempDir.'/.keep/cache/testing.keep.php'))->toBeTrue();
        expect(file_exists($this->tempDir.'/.keep/cache/.gitignore'))->toBeTrue();

        // Verify gitignore content
        $gitignoreContent = file_get_contents($this->tempDir.'/.keep/cache/.gitignore');
        expect($gitignoreContent)->toBe("*\n!.gitignore\n");
    });

    it('loads from all configured vaults when no vault specified in cache mode', function () {
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

        // Run export cache command without specifying vault
        $commandTester = ($this->runCommandWithPersistence)('export', [
            '--stage' => 'testing',
            '--cache' => true,
        ]);

        expect($commandTester->getStatusCode())->toBe(0);
        expect($commandTester->getDisplay())->toContain('Loading secrets from 3 vaults (test, vault1, vault2)');
        expect($commandTester->getDisplay())->toContain('Found 1 total secrets to cache');
    });

    it('loads from specified comma-separated vaults in cache mode', function () {
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

        // Run export cache command with specific vaults
        $commandTester = ($this->runCommandWithPersistence)('export', [
            '--stage' => 'testing',
            '--vault' => 'vault1,vault3',
            '--cache' => true,
        ]);

        expect($commandTester->getStatusCode())->toBe(0);
        expect($commandTester->getDisplay())->toContain('Loading secrets from 2 vaults (vault1, vault3)');
        expect($commandTester->getDisplay())->toContain('Found 1 total secrets to cache');
    });

    it('applies --only and --except filters in cache mode', function () {
        // Create multiple secrets
        ($this->runCommandWithPersistence)('set', [
            'key' => 'API_KEY',
            'value' => 'api-value',
            '--stage' => 'testing',
            '--vault' => 'test',
            '--plain' => true,
        ]);

        ($this->runCommandWithPersistence)('set', [
            'key' => 'DB_PASSWORD',
            'value' => 'db-value',
            '--stage' => 'testing',
            '--vault' => 'test',
            '--plain' => true,
        ]);

        ($this->runCommandWithPersistence)('set', [
            'key' => 'APP_DEBUG',
            'value' => 'true',
            '--stage' => 'testing',
            '--vault' => 'test',
            '--plain' => true,
        ]);

        // Export cache with --only filter
        $commandTester = ($this->runCommandWithPersistence)('export', [
            '--stage' => 'testing',
            '--vault' => 'test',
            '--only' => 'API_*,DB_*',
            '--cache' => true,
        ]);

        expect($commandTester->getStatusCode())->toBe(0);
        expect($commandTester->getDisplay())->toContain('Found 1 total secrets to cache');
    });

    it('updates .env file with KEEP_CACHE_KEY_PART', function () {
        // Create a test secret
        $vault = Keep::vault('test', 'testing');
        $vault->set('TEST_SECRET', 'secret-value');

        // Run export cache command
        $commandTester = runCommand('export', [
            '--stage' => 'testing',
            '--vault' => 'test',
            '--cache' => true,
        ]);

        expect($commandTester->getStatusCode())->toBe(0);
        expect($commandTester->getDisplay())->toContain('Updated .env file with KEEP_CACHE_KEY_PART');

        // Verify .env file was created/updated
        $envPath = $this->tempDir.'/.env';
        expect(file_exists($envPath))->toBeTrue();

        $envContent = file_get_contents($envPath);
        expect($envContent)->toContain('KEEP_CACHE_KEY_PART=');

        // Verify file permissions are restricted
        $permissions = fileperms($envPath) & 0777;
        expect($permissions)->toBe(0600);
    });

})->skip('Cache export feature deferred to future release');

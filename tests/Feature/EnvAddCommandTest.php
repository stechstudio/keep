<?php

use STS\Keep\Data\Settings;

describe('EnvAddCommand', function () {

    beforeEach(function () {
        $this->tempDir = createTempKeepDir();

        // Create .keep directory and settings to initialize Keep
        mkdir('.keep');
        mkdir('.keep/vaults');

        $settings = [
            'app_name' => 'test-app',
            'namespace' => 'test-app',
            'default_vault' => 'test',
            'envs' => ['testing', 'production'],
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
    });

    afterEach(function () {
        if (isset($this->tempDir)) {
            cleanupTempDir($this->tempDir);
        }
    });

    describe('adding custom environments', function () {

        it('adds a new custom environment via argument', function () {
            $initialSettings = Settings::load();

            // Use a unique environment name for this test
            $envName = 'test-env-'.uniqid();

            expect($initialSettings->envs())->not->toContain($envName);

            // Add a custom environment (auto-confirmed in non-interactive mode)
            $commandTester = runCommand('env:add', [
                'name' => $envName,
            ]);

            expect($commandTester->getStatusCode())->toBe(0);

            // Verify environment was added
            $updatedSettings = Settings::load();
            expect($updatedSettings->envs())->toContain($envName);
        });

        it('validates environment name format', function () {
            // Try to add an invalid environment name
            $commandTester = runCommand('env:add', [
                'name' => 'invalid env!', // Contains space and special char
            ]);

            expect($commandTester->getStatusCode())->toBe(1);
            expect($commandTester->getDisplay())->toContain('can only contain');

            // Verify environment was not added
            $settings = Settings::load();
            expect($settings->envs())->not->toContain('invalid env!');
        });

        it('prevents duplicate environment names', function () {
            $settings = Settings::load();
            $existingEnv = $settings->envs()[0]; // Get first existing environment

            // Try to add a duplicate
            $commandTester = runCommand('env:add', [
                'name' => $existingEnv,
            ]);

            expect($commandTester->getStatusCode())->toBe(1);

            // Count should remain the same
            $updatedSettings = Settings::load();
            expect(count($updatedSettings->envs()))->toBe(count($settings->envs()));
        });

        it('allows lowercase alphanumeric names with hyphens and underscores', function () {
            $validNames = ['dev-2', 'test_env', 'qa1', 'prod-backup'];

            foreach ($validNames as $envName) {
                // Remove environment if it exists (cleanup from previous tests)
                $settings = Settings::load();
                $envs = array_diff($settings->envs(), [$envName]);
                Settings::fromArray([
                    'app_name' => $settings->appName(),
                    'namespace' => $settings->namespace(),
                    'envs' => array_values($envs),
                    'default_vault' => $settings->defaultVault(),
                    'created_at' => $settings->createdAt(),
                ])->save();

                // Add the environment
                $commandTester = runCommand('env:add', [
                    'name' => $envName,
                ]);

                expect($commandTester->getStatusCode())->toBe(0);

                // Verify it was added
                $updatedSettings = Settings::load();
                expect($updatedSettings->envs())->toContain($envName);
            }
        });
    });

    describe('integration with other commands', function () {

        it('makes custom environment available for use', function () {
            // Add a unique custom environment
            $envName = 'integration-'.uniqid();
            $commandTester = runCommand('env:add', ['name' => $envName]);

            expect($commandTester->getStatusCode())->toBe(0);
            expect($commandTester->getDisplay())->toContain("Environment '{$envName}' has been added successfully");

            // Verify the custom environment is persisted in settings
            $settings = Settings::load();
            expect($settings->envs())->toContain($envName);

            // Verify multiple custom environments can be added
            $secondEnv = 'secondary-'.uniqid();
            $secondCommand = runCommand('env:add', ['name' => $secondEnv]);

            expect($secondCommand->getStatusCode())->toBe(0);

            $updatedSettings = Settings::load();
            expect($updatedSettings->envs())->toContain($envName);
            expect($updatedSettings->envs())->toContain($secondEnv);
        });
    });
});

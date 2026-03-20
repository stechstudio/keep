<?php

use STS\Keep\Data\Settings;

describe('EnvRemoveCommand', function () {

    beforeEach(function () {
        $this->tempDir = createTempKeepDir();

        mkdir('.keep');
        mkdir('.keep/vaults');

        $settings = [
            'app_name' => 'test-app',
            'namespace' => 'test-app',
            'default_vault' => 'test',
            'envs' => ['local', 'staging', 'production', 'custom-env', 'qa'],
            'created_at' => date('c'),
            'version' => '1.0',
        ];

        file_put_contents('.keep/settings.json', json_encode($settings, JSON_PRETTY_PRINT));

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

    describe('removing custom environments', function () {

        it('removes a custom environment with --force', function () {
            $initialSettings = Settings::load();
            expect($initialSettings->envs())->toContain('custom-env');

            $commandTester = runCommand('env:remove', [
                'name' => 'custom-env',
                '--force' => true,
            ]);

            expect($commandTester->getStatusCode())->toBe(0);

            $updatedSettings = Settings::load();
            expect($updatedSettings->envs())->not->toContain('custom-env');
        });

        it('preserves other environments when removing one', function () {
            $commandTester = runCommand('env:remove', [
                'name' => 'custom-env',
                '--force' => true,
            ]);

            expect($commandTester->getStatusCode())->toBe(0);

            $updatedSettings = Settings::load();
            expect($updatedSettings->envs())->toContain('local');
            expect($updatedSettings->envs())->toContain('staging');
            expect($updatedSettings->envs())->toContain('production');
            expect($updatedSettings->envs())->toContain('qa');
        });

        it('shows success message after removal', function () {
            $commandTester = runCommand('env:remove', [
                'name' => 'custom-env',
                '--force' => true,
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('custom-env');
            expect($output)->toContain('removed');
        });
    });

    describe('system environment protection', function () {

        it('prevents removing local environment', function () {
            $commandTester = runCommand('env:remove', [
                'name' => 'local',
                '--force' => true,
            ]);

            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Cannot remove system environment');

            $settings = Settings::load();
            expect($settings->envs())->toContain('local');
        });

        it('prevents removing staging environment', function () {
            $commandTester = runCommand('env:remove', [
                'name' => 'staging',
                '--force' => true,
            ]);

            expect($commandTester->getStatusCode())->toBe(1);

            $settings = Settings::load();
            expect($settings->envs())->toContain('staging');
        });

        it('prevents removing production environment', function () {
            $commandTester = runCommand('env:remove', [
                'name' => 'production',
                '--force' => true,
            ]);

            expect($commandTester->getStatusCode())->toBe(1);

            $settings = Settings::load();
            expect($settings->envs())->toContain('production');
        });
    });

    describe('error handling', function () {

        it('shows error for non-existent environment', function () {
            $commandTester = runCommand('env:remove', [
                'name' => 'nonexistent',
                '--force' => true,
            ]);

            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('not found');
        });

        it('handles no custom environments gracefully', function () {
            $settings = [
                'app_name' => 'test-app',
                'namespace' => 'test-app',
                'default_vault' => 'test',
                'envs' => ['local', 'staging', 'production'],
                'created_at' => date('c'),
                'version' => '1.0',
            ];
            file_put_contents('.keep/settings.json', json_encode($settings, JSON_PRETTY_PRINT));

            $commandTester = runCommand('env:remove', [
                '--force' => true,
            ]);

            expect($commandTester->getStatusCode())->toBe(0);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('No custom environments');
        });
    });

    describe('confirmation behavior', function () {

        it('cancels removal without --force in non-interactive mode', function () {
            $commandTester = runCommand('env:remove', [
                'name' => 'custom-env',
            ]);

            // Non-interactive mode auto-declines confirmation
            $settings = Settings::load();
            expect($settings->envs())->toContain('custom-env');
        });

        it('skips confirmation with --force flag', function () {
            $commandTester = runCommand('env:remove', [
                'name' => 'custom-env',
                '--force' => true,
            ]);

            expect($commandTester->getStatusCode())->toBe(0);

            $settings = Settings::load();
            expect($settings->envs())->not->toContain('custom-env');
        });
    });
});

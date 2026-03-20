<?php

describe('VaultDeleteCommand', function () {

    beforeEach(function () {
        $this->tempDir = createTempKeepDir();

        mkdir('.keep');
        mkdir('.keep/vaults');

        $settings = [
            'app_name' => 'test-app',
            'namespace' => 'test-app',
            'default_vault' => 'primary',
            'envs' => ['testing', 'production'],
            'created_at' => date('c'),
            'version' => '1.0',
        ];

        file_put_contents('.keep/settings.json', json_encode($settings, JSON_PRETTY_PRINT));

        $primaryVault = [
            'driver' => 'test',
            'name' => 'Primary Vault',
            'namespace' => 'test-app',
        ];

        $secondaryVault = [
            'driver' => 'test',
            'name' => 'Secondary Vault',
            'namespace' => 'test-app',
        ];

        file_put_contents('.keep/vaults/primary.json', json_encode($primaryVault, JSON_PRETTY_PRINT));
        file_put_contents('.keep/vaults/secondary.json', json_encode($secondaryVault, JSON_PRETTY_PRINT));
    });

    afterEach(function () {
        if (isset($this->tempDir)) {
            cleanupTempDir($this->tempDir);
        }
    });

    describe('deleting a vault', function () {

        it('deletes a non-default vault with --force', function () {
            expect(file_exists('.keep/vaults/secondary.json'))->toBeTrue();

            $commandTester = runCommand('vault:delete', [
                'vault' => 'secondary',
                '--force' => true,
            ]);

            expect($commandTester->getStatusCode())->toBe(0);
            expect(file_exists('.keep/vaults/secondary.json'))->toBeFalse();
        });

        it('shows success message after deletion', function () {
            $commandTester = runCommand('vault:delete', [
                'vault' => 'secondary',
                '--force' => true,
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('secondary');
            expect($output)->toContain('deleted');
        });

        it('shows vault details before deletion', function () {
            $commandTester = runCommand('vault:delete', [
                'vault' => 'secondary',
                '--force' => true,
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Secondary Vault');
        });
    });

    describe('protection against deleting default vault', function () {

        it('prevents deleting the default vault', function () {
            $commandTester = runCommand('vault:delete', [
                'vault' => 'primary',
                '--force' => true,
            ]);

            expect($commandTester->getStatusCode())->toBe(1);
            expect(file_exists('.keep/vaults/primary.json'))->toBeTrue();

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Cannot delete the default vault');
        });
    });

    describe('error handling', function () {

        it('shows error for non-existent vault', function () {
            $commandTester = runCommand('vault:delete', [
                'vault' => 'nonexistent',
                '--force' => true,
            ]);

            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('not found');
        });

        it('handles no configured vaults', function () {
            unlink('.keep/vaults/primary.json');
            unlink('.keep/vaults/secondary.json');

            $commandTester = runCommand('vault:delete', [
                'vault' => 'anything',
                '--force' => true,
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('No vaults');
        });
    });

    describe('confirmation behavior', function () {

        it('cancels deletion without --force in non-interactive mode', function () {
            $commandTester = runCommand('vault:delete', [
                'vault' => 'secondary',
            ]);

            // Non-interactive mode auto-declines confirmation
            expect(file_exists('.keep/vaults/secondary.json'))->toBeTrue();
        });

        it('skips confirmation with --force flag', function () {
            $commandTester = runCommand('vault:delete', [
                'vault' => 'secondary',
                '--force' => true,
            ]);

            expect($commandTester->getStatusCode())->toBe(0);
            expect(file_exists('.keep/vaults/secondary.json'))->toBeFalse();
        });
    });
});

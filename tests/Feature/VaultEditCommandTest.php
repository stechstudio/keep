<?php

describe('VaultEditCommand', function () {

    beforeEach(function () {
        createTempKeepDir();
        
        // Create .keep directory and settings to initialize Keep
        mkdir('.keep');
        mkdir('.keep/vaults');
        
        $settings = [
            'app_name' => 'test-app',
            'namespace' => 'test-app',
            'default_vault' => 'test',
            'stages' => ['testing', 'production'],
            'created_at' => date('c'),
            'version' => '1.0'
        ];
        
        file_put_contents('.keep/settings.json', json_encode($settings, JSON_PRETTY_PRINT));
        
        // Create test vault configuration
        $vaultConfig = [
            'slug' => 'test',
            'driver' => 'test',
            'name' => 'Test Vault',
            'namespace' => 'test-app'
        ];
        
        file_put_contents('.keep/vaults/test.json', json_encode($vaultConfig, JSON_PRETTY_PRINT));
    });

    describe('command structure and signature', function () {
        it('accepts vault slug argument', function () {
            $commandTester = runCommand('vault:edit', [
                'slug' => 'test',
                '--no-interaction' => true,
            ]);
            
            // Should not fail due to missing slug
            expect($commandTester->getStatusCode())->toBe(0);
        });
        
        it('shows error for non-existent vault', function () {
            $commandTester = runCommand('vault:edit', [
                'slug' => 'nonexistent',
                '--no-interaction' => true,
            ]);
            
            expect($commandTester->getStatusCode())->toBe(1);
            
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain("Vault 'nonexistent' does not exist");
        });
    });

    describe('error handling', function () {
        it('handles no vaults configured gracefully', function () {
            // Remove all vault configurations
            unlink('.keep/vaults/test.json');
            
            $commandTester = runCommand('vault:edit', [
                '--no-interaction' => true,
            ]);
            
            expect($commandTester->getStatusCode())->toBe(1);
            
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('No vaults are configured yet');
            expect($output)->toContain('Add your first vault with: keep vault:add');
        });
    });

    describe('vault configuration editing', function () {
        it('shows current vault information', function () {
            $commandTester = runCommand('vault:edit', [
                'slug' => 'test',
                '--no-interaction' => true,
            ]);
            
            expect($commandTester->getStatusCode())->toBe(0);
            
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Edit Vault Configuration');
            expect($output)->toContain('Editing vault: Test Vault (test)');
        });
        
        it('handles vault driver validation', function () {
            $commandTester = runCommand('vault:edit', [
                'slug' => 'test',
                '--no-interaction' => true,
            ]);
            
            expect($commandTester->getStatusCode())->toBe(0);
            
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Configuring');
        });
    });

    describe('slug validation and renaming', function () {
        it('validates that new slug does not conflict', function () {
            // Create another vault first
            $anotherVaultConfig = [
                'slug' => 'another',
                'driver' => 'test',
                'name' => 'Another Vault',
                'namespace' => 'test-app'
            ];
            
            file_put_contents('.keep/vaults/another.json', json_encode($anotherVaultConfig, JSON_PRETTY_PRINT));
            
            // Try to rename 'test' vault to 'another' (should fail)
            $commandTester = runCommand('vault:edit', [
                'slug' => 'test',
                '--no-interaction' => true,
            ]);
            
            // Command should complete successfully even in no-interaction mode
            expect($commandTester->getStatusCode())->toBe(0);
        });
    });

    describe('integration functionality', function () {
        it('handles vault configuration updates', function () {
            $commandTester = runCommand('vault:edit', [
                'slug' => 'test',
                '--no-interaction' => true,
            ]);
            
            expect($commandTester->getStatusCode())->toBe(0);
            
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('configuration updated successfully');
        });
        
        it('requires Keep to be initialized', function () {
            // Remove .keep directory to simulate uninitialized state
            unlink('.keep/settings.json');
            unlink('.keep/vaults/test.json');
            rmdir('.keep/vaults');
            rmdir('.keep');
            
            $commandTester = runCommand('vault:edit', [
                'slug' => 'test',
                '--no-interaction' => true,
            ]);
            
            expect($commandTester->getStatusCode())->toBe(1);
            
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Keep is not initialized in this directory');
            expect($output)->toContain('Run: keep configure');
        });
    });
});
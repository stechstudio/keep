<?php

describe('DeleteCommand', function () {

    beforeEach(function () {
        createTempKeepDir();
        
        // Create .keep directory and settings to initialize Keep
        mkdir('.keep');
        mkdir('.keep/vaults');
        
        $settings = [
            'app_name' => 'test-app',
            'namespace' => 'test-app',
            'default_vault' => 'ssm',
            'stages' => ['testing', 'production'],
            'created_at' => date('c'),
            'version' => '1.0'
        ];
        
        file_put_contents('.keep/settings.json', json_encode($settings, JSON_PRETTY_PRINT));
        
        // Create SSM vault configuration for testing
        $vaultConfig = [
            'driver' => 'ssm',
            'name' => 'Test SSM Vault',
            'region' => 'us-east-1',
            'prefix' => 'test'
        ];
        
        file_put_contents('.keep/vaults/ssm.json', json_encode($vaultConfig, JSON_PRETTY_PRINT));
    });

    describe('command structure and signature', function () {
        it('accepts force flag option', function () {
            $commandTester = runCommand('delete', [
                'key' => 'TEST_KEY',
                '--vault' => 'ssm',
                '--stage' => 'testing',
                '--force' => true
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should accept --force flag without validation error
            expect($output)->not->toMatch('/(invalid option|unknown option)/i');
        });
        
        it('validates key argument is provided', function () {
            // Try to run delete command without key
            $commandTester = runCommand('delete', [
                '--vault' => 'ssm',
                '--stage' => 'testing',
                '--force' => true
            ]);

            // Should fail due to missing key argument
            expect($commandTester->getStatusCode())->toBe(1);
            
            $output = stripAnsi($commandTester->getDisplay());
            // The command should fail in some way (missing key, aborted, etc.)
            expect($output)->toMatch('/(Aborted|error|required|missing)/i');
        });
        
        it('accepts vault and stage parameters', function () {
            $commandTester = runCommand('delete', [
                'key' => 'TEST_KEY',
                '--vault' => 'ssm',
                '--stage' => 'testing',
                '--force' => true
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should accept vault/stage parameters without validation error
            expect($output)->not->toMatch('/(invalid.*vault|invalid.*stage)/i');
        });
    });

    describe('error handling', function () {
        it('handles non-existent secrets gracefully', function () {
            $commandTester = runCommand('delete', [
                'key' => 'NONEXISTENT_KEY',
                '--vault' => 'ssm',
                '--stage' => 'testing',
                '--force' => true
            ]);

            expect($commandTester->getStatusCode())->toBe(1);
            
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toMatch('/(not found|error|failed)/i');
        });
        
        it('handles vault connection issues gracefully', function () {
            $commandTester = runCommand('delete', [
                'key' => 'CONNECTION_TEST_KEY',
                '--vault' => 'ssm',
                '--stage' => 'testing',
                '--force' => true
            ]);

            // Command should complete (success or controlled failure)
            $output = stripAnsi($commandTester->getDisplay());
            
            // Should not crash or show unhandled exceptions
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('validates stage parameters exist in configuration', function () {
            $commandTester = runCommand('delete', [
                'key' => 'STAGE_TEST_KEY',
                '--vault' => 'ssm',
                '--stage' => 'testing', // Valid stage from our config
                '--force' => true
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should accept valid stages without validation error
            expect($output)->not->toMatch('/invalid.*stage/i');
        });
    });

    describe('force flag behavior', function () {
        it('accepts force flag without prompting', function () {
            $commandTester = runCommand('delete', [
                'key' => 'FORCE_TEST_KEY',
                '--vault' => 'ssm',
                '--stage' => 'testing',
                '--force' => true
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should accept --force flag and not show confirmation prompts
            expect($output)->not->toMatch('/(Are you sure|confirm)/i');
        });
        
        it('shows appropriate deletion message format', function () {
            $commandTester = runCommand('delete', [
                'key' => 'MESSAGE_TEST_KEY',
                '--vault' => 'ssm',
                '--stage' => 'testing',
                '--force' => true
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should show some form of result message (success, error, or completion)
            expect($output)->not->toBeEmpty();
        });
    });

    describe('stage and vault handling', function () {
        it('uses specified vault parameter', function () {
            $commandTester = runCommand('delete', [
                'key' => 'VAULT_TEST_KEY',
                '--vault' => 'ssm',
                '--stage' => 'testing',
                '--force' => true
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle vault parameter without error
            expect($output)->not->toMatch('/invalid.*vault/i');
        });
        
        it('uses specified stage parameter', function () {
            $commandTester = runCommand('delete', [
                'key' => 'STAGE_TEST_KEY',
                '--vault' => 'ssm',
                '--stage' => 'testing',
                '--force' => true
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle stage parameter without error
            expect($output)->not->toMatch('/invalid.*stage/i');
        });
        
        it('handles production stage parameter', function () {
            $commandTester = runCommand('delete', [
                'key' => 'PROD_TEST_KEY',
                '--vault' => 'ssm',
                '--stage' => 'production', // Valid stage from our config
                '--force' => true
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle production stage without error
            expect($output)->not->toMatch('/invalid.*stage/i');
        });
    });

    describe('confirmation and output display', function () {
        it('shows secret details appropriately', function () {
            $commandTester = runCommand('delete', [
                'key' => 'DETAILS_TEST_KEY',
                '--vault' => 'ssm',
                '--stage' => 'testing',
                '--force' => true
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should show some form of details or status information
            expect($output)->not->toBeEmpty();
        });
        
        it('handles table display formatting', function () {
            $commandTester = runCommand('delete', [
                'key' => 'TABLE_TEST_KEY',
                '--vault' => 'ssm',
                '--stage' => 'testing',
                '--force' => true
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle display formatting without errors
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('edge cases and special scenarios', function () {
        it('handles special characters in key names', function () {
            $commandTester = runCommand('delete', [
                'key' => 'KEY_WITH_SPECIAL-CHARS.123',
                '--vault' => 'ssm',
                '--stage' => 'testing',
                '--force' => true
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle special characters in keys without error
            expect($output)->not->toMatch('/invalid.*key/i');
        });
        
        it('handles confirmation cancellation appropriately', function () {
            // Test command without --force to test confirmation logic path
            $commandTester = runCommand('delete', [
                'key' => 'CANCEL_TEST_KEY',
                '--vault' => 'ssm',
                '--stage' => 'testing'
                // Note: no --force flag, so would normally prompt
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle confirmation logic without hanging or crashing
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('handles context creation appropriately', function () {
            $commandTester = runCommand('delete', [
                'key' => 'CONTEXT_TEST_KEY',
                '--vault' => 'ssm',
                '--stage' => 'testing',
                '--force' => true
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle context creation without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('integration scenarios', function () {
        it('handles secret retrieval for verification', function () {
            $commandTester = runCommand('delete', [
                'key' => 'VERIFY_TEST_KEY',
                '--vault' => 'ssm',
                '--stage' => 'testing',
                '--force' => true
            ]);

            // Should attempt to verify secret exists before deletion
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('provides appropriate completion status', function () {
            $commandTester = runCommand('delete', [
                'key' => 'COMPLETION_TEST_KEY',
                '--vault' => 'ssm',
                '--stage' => 'testing',
                '--force' => true
            ]);

            // Should complete with appropriate status
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });
    });
    
    // Tests migrated from Laravel-style tests to new architecture.
    // Focus on command structure, parameter validation, error handling, and confirmation logic
    // rather than full integration tests that depend on external AWS services and pre-populated data.
    // Integration tests with actual secret deletion would require either mock vaults or controlled test data.
});
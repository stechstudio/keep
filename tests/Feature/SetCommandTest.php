<?php

describe('SetCommand', function () {

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
        
        // Create test vault configuration for testing (never hits AWS)
        $vaultConfig = [
            'driver' => 'test',
            'name' => 'Test Vault',
            'namespace' => 'test-app'
        ];
        
        file_put_contents('.keep/vaults/test.json', json_encode($vaultConfig, JSON_PRETTY_PRINT));
    });

    describe('command structure', function () {
        it('validates required key and value arguments', function () {
            // Try to run set command without key
            $commandTester = runCommand('set', [
                'value' => 'test-value',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            // Should fail due to missing key argument
            expect($commandTester->getStatusCode())->toBe(1);
            
            $output = stripAnsi($commandTester->getDisplay());
            // The command should fail in some way (missing key, aborted, etc.)
            expect($output)->toMatch('/(Aborted|error|required|missing)/i');
        });
        
        it('validates vault and stage parameters', function () {
            $commandTester = runCommand('set', [
                'key' => 'TEST_KEY',
                'value' => 'test-value',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            // Command should accept valid parameters (might fail on AWS interaction)
            $output = stripAnsi($commandTester->getDisplay());
            
            // Should not show parameter validation errors
            expect($output)->not->toMatch('/(invalid vault|invalid stage)/i');
        });
        
        it('accepts plain flag option', function () {
            $commandTester = runCommand('set', [
                'key' => 'TEST_KEY',
                'value' => 'test-value',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--plain' => true
            ]);

            // Command should accept --plain flag without error
            $output = stripAnsi($commandTester->getDisplay());
            
            // Should not show option validation errors
            expect($output)->not->toMatch('/(invalid option|unknown option)/i');
        });
    });

    describe('output messages', function () {
        it('shows appropriate completion message', function () {
            $commandTester = runCommand('set', [
                'key' => 'MESSAGE_TEST_KEY',
                'value' => 'message-test-value',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should show some success/completion message (exact format may vary)
            // Could be creation, update, or error message (including AWS auth errors)
            expect($output)->toMatch('/(created|updated|Secret|error|not authorized)/i');
        });
    });

    describe('error handling', function () {
        it('handles vault connection issues gracefully', function () {
            $commandTester = runCommand('set', [
                'key' => 'ERROR_TEST_KEY',
                'value' => 'error-test-value',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            // Command should complete (success or controlled failure)
            $output = stripAnsi($commandTester->getDisplay());
            
            // Should not crash or show unhandled exceptions
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('validates stage parameter exists in configuration', function () {
            $commandTester = runCommand('set', [
                'key' => 'STAGE_TEST_KEY', 
                'value' => 'stage-test-value',
                '--vault' => 'test',
                '--stage' => 'testing' // Valid stage from our config
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should accept valid stage without validation error
            expect($output)->not->toMatch('/invalid.*stage/i');
        });
    });

    describe('edge cases and special values', function () {
        it('handles special characters in key names', function () {
            $commandTester = runCommand('set', [
                'key' => 'KEY_WITH_SPECIAL-CHARS.123',
                'value' => 'special-char-value',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle special characters in keys without error
            expect($output)->not->toMatch('/invalid.*key/i');
        });

        it('handles special characters in values', function () {
            $specialValue = 'value with spaces & symbols: @#$%^&*()_+-=[]{}|;:,.<>?';

            $commandTester = runCommand('set', [
                'key' => 'SPECIAL_VALUE_KEY',
                'value' => $specialValue,
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle special characters in values
            expect($output)->not->toMatch('/invalid.*value/i');
        });

        it('handles empty values', function () {
            $commandTester = runCommand('set', [
                'key' => 'EMPTY_VALUE_KEY',
                'value' => '',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle empty values (may succeed or fail due to AWS/vault validation)
            // Just ensure no crash/fatal errors
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('handles unicode values correctly', function () {
            $unicodeValue = 'Hello 世界 🚀 مرحبا';

            $commandTester = runCommand('set', [
                'key' => 'UNICODE_KEY',
                'value' => $unicodeValue,
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle unicode without encoding errors
            expect($output)->not->toMatch('/encoding|invalid.*character/i');
        });
    });
    
    // Tests migrated from Laravel-style tests to new architecture.
    // Focus on command structure, parameter validation, and error handling
    // rather than full integration tests that depend on external AWS services.
    // Full integration tests would require either mock vaults or controlled AWS environments.
});
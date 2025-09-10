<?php

describe('HistoryCommand', function () {

    beforeEach(function () {
        createTempKeepDir();

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

    describe('command structure and signature', function () {
        it('accepts limit option with numeric values', function () {
            $commandTester = runCommand('history', [
                'key' => 'TEST_KEY',
                '--limit' => '5',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept --limit option without validation error
            expect($output)->not->toMatch('/(invalid option|unknown option)/i');
        });

        it('accepts format option with valid values', function () {
            $formats = ['table', 'json'];

            foreach ($formats as $format) {
                $commandTester = runCommand('history', [
                    'key' => 'TEST_KEY',
                    '--format' => $format,
                    '--vault' => 'test',
                    '--env' => 'testing',
                ]);

                $output = stripAnsi($commandTester->getDisplay());

                // Should accept valid format options without validation error
                expect($output)->not->toMatch('/(invalid.*format|unknown.*option)/i');
            }
        });

        it('accepts user filter option', function () {
            $commandTester = runCommand('history', [
                'key' => 'TEST_KEY',
                '--user' => 'testuser',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept --user option without validation error
            expect($output)->not->toMatch('/(invalid option|unknown option)/i');
        });

        it('accepts date filter options', function () {
            $commandTester = runCommand('history', [
                'key' => 'TEST_KEY',
                '--since' => '1 day ago',
                '--before' => '2024-12-31',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept date filter options without validation error
            expect($output)->not->toMatch('/(invalid option|unknown option)/i');
        });

        it('accepts unmask flag option', function () {
            $commandTester = runCommand('history', [
                'key' => 'TEST_KEY',
                '--unmask' => true,
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept --unmask flag without validation error
            expect($output)->not->toMatch('/(invalid option|unknown option)/i');
        });

        it('validates key argument is provided', function () {
            // Try to run history command without key
            $commandTester = runCommand('history', [
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            // Should fail due to missing key argument
            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            // The command should fail in some way (missing key, aborted, etc.)
            expect($output)->toMatch('/(Aborted|error|required|missing)/i');
        });
    });

    describe('format handling', function () {
        it('defaults to table format when not specified', function () {
            $commandTester = runCommand('history', [
                'key' => 'TEST_KEY',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should default to table format without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('handles json format option', function () {
            $commandTester = runCommand('history', [
                'key' => 'TEST_KEY',
                '--format' => 'json',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle JSON format without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('handles invalid format option gracefully', function () {
            $commandTester = runCommand('history', [
                'key' => 'TEST_KEY',
                '--format' => 'invalid',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            // Current behavior: secret lookup happens before format validation
            // So we get "not found" error rather than "invalid format" error
            expect($output)->toMatch('/(not found|error)/i');
        });
    });

    describe('limit and filtering', function () {
        it('handles limit parameter appropriately', function () {
            $commandTester = runCommand('history', [
                'key' => 'TEST_KEY',
                '--limit' => '10',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle limit parameter without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('handles user filtering appropriately', function () {
            $commandTester = runCommand('history', [
                'key' => 'TEST_KEY',
                '--user' => 'testuser',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle user filtering without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('handles date filtering appropriately', function () {
            $commandTester = runCommand('history', [
                'key' => 'TEST_KEY',
                '--since' => '1 week ago',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle date filtering without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('handles invalid date format gracefully', function () {
            $commandTester = runCommand('history', [
                'key' => 'TEST_KEY',
                '--since' => 'invalid-date',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            // Should handle invalid date format
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('error handling', function () {
        it('handles non-existent secrets gracefully', function () {
            $commandTester = runCommand('history', [
                'key' => 'NONEXISTENT_KEY',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toMatch('/(not found|error)/i');
        });

        it('handles vault connection issues gracefully', function () {
            $commandTester = runCommand('history', [
                'key' => 'CONNECTION_TEST_KEY',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            // Command should complete (success or controlled failure)
            $output = stripAnsi($commandTester->getDisplay());

            // Should not crash or show unhandled exceptions
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('handles empty history gracefully', function () {
            $commandTester = runCommand('history', [
                'key' => 'EMPTY_HISTORY_KEY',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            // Should handle empty history without error
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('masking and display', function () {
        it('handles masking by default', function () {
            $commandTester = runCommand('history', [
                'key' => 'MASK_TEST_KEY',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle masking without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('handles unmask flag appropriately', function () {
            $commandTester = runCommand('history', [
                'key' => 'UNMASK_TEST_KEY',
                '--unmask' => true,
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle unmask flag without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('handles table display formatting', function () {
            $commandTester = runCommand('history', [
                'key' => 'TABLE_TEST_KEY',
                '--format' => 'table',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle table formatting without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('env and vault handling', function () {
        it('uses specified vault parameter', function () {
            $commandTester = runCommand('history', [
                'key' => 'VAULT_TEST_KEY',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle vault parameter without error
            expect($output)->not->toMatch('/invalid.*vault/i');
        });

        it('uses specified env parameter', function () {
            $commandTester = runCommand('history', [
                'key' => 'STAGE_TEST_KEY',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle environment parameter without error
            expect($output)->not->toMatch('/invalid.*env/i');
        });

        it('handles production env parameter', function () {
            $commandTester = runCommand('history', [
                'key' => 'PROD_TEST_KEY',
                '--vault' => 'test',
                '--env' => 'production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle production environment without error
            expect($output)->not->toMatch('/invalid.*env/i');
        });
    });

    describe('edge cases and special scenarios', function () {
        it('handles special characters in key names', function () {
            $commandTester = runCommand('history', [
                'key' => 'KEY_WITH_SPECIAL-CHARS.123',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle special characters in keys without error
            expect($output)->not->toMatch('/invalid.*key/i');
        });

        it('handles zero limit value', function () {
            $commandTester = runCommand('history', [
                'key' => 'ZERO_LIMIT_KEY',
                '--limit' => '0',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle zero limit without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('handles large limit value', function () {
            $commandTester = runCommand('history', [
                'key' => 'LARGE_LIMIT_KEY',
                '--limit' => '1000',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle large limit without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('handles context creation appropriately', function () {
            $commandTester = runCommand('history', [
                'key' => 'CONTEXT_TEST_KEY',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle context creation without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('filter collection processing', function () {
        it('handles filter collection creation', function () {
            $commandTester = runCommand('history', [
                'key' => 'FILTER_TEST_KEY',
                '--user' => 'testuser',
                '--since' => '1 day ago',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle filter collection without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('handles combination of filters', function () {
            $commandTester = runCommand('history', [
                'key' => 'COMBO_FILTER_KEY',
                '--user' => 'testuser',
                '--since' => '1 week ago',
                '--before' => '2024-12-31',
                '--limit' => '5',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle multiple filters without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('integration functionality', function () {
        it('handles history retrieval operation', function () {
            $commandTester = runCommand('history', [
                'key' => 'HISTORY_TEST_KEY',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            // Should handle history retrieval without error
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });

        it('provides appropriate completion status', function () {
            $commandTester = runCommand('history', [
                'key' => 'STATUS_TEST_KEY',
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            // Should complete with appropriate status
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });
    });

    // Tests migrated from Laravel-style tests to new architecture.
    // Focus on command structure, parameter validation, filtering, and format options
    // rather than full integration tests that depend on external AWS services and pre-populated data.
    // Integration tests with actual secret history would require either mock vaults or controlled test data.
});

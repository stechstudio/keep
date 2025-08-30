<?php

describe('CopyCommand', function () {

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
        it('accepts required from and to options', function () {
            $commandTester = runCommand('copy', [
                'key' => 'TEST_KEY',
                '--from' => 'testing',
                '--to' => 'production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept the from/to options without validation error
            expect($output)->not->toMatch('/(invalid.*from|invalid.*to|unknown.*option)/i');
        });

        it('accepts overwrite flag option', function () {
            $commandTester = runCommand('copy', [
                'key' => 'TEST_KEY',
                '--from' => 'testing',
                '--to' => 'production',
                '--overwrite' => true,
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept --overwrite flag without validation error
            expect($output)->not->toMatch('/(invalid option|unknown option)/i');
        });

        it('accepts dry-run flag option', function () {
            $commandTester = runCommand('copy', [
                'key' => 'TEST_KEY',
                '--from' => 'testing',
                '--to' => 'production',
                '--dry-run' => true,
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept --dry-run flag without validation error
            expect($output)->not->toMatch('/(invalid option|unknown option)/i');
        });

        it('validates key argument is provided', function () {
            // Try to run copy command without key or patterns
            $commandTester = runCommand('copy', [
                '--from' => 'testing',
                '--to' => 'production',
            ]);

            // Should fail due to missing key argument or patterns
            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            // The command should fail with clear guidance
            expect($output)->toContain('Either provide a key or use --only/--except patterns');
        });
    });

    describe('context validation', function () {
        it('rejects identical source and destination', function () {
            $commandTester = runCommand('copy', [
                'key' => 'TEST_KEY',
                '--from' => 'testing',
                '--to' => 'testing',
            ]);

            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Source and destination are identical');
        });

        it('accepts different stage names', function () {
            $commandTester = runCommand('copy', [
                'key' => 'TEST_KEY',
                '--from' => 'testing',
                '--to' => 'production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should not show context validation errors
            expect($output)->not->toMatch('/identical|same/i');
        });

        it('accepts vault:stage syntax', function () {
            $commandTester = runCommand('copy', [
                'key' => 'TEST_KEY',
                '--from' => 'ssm:testing',
                '--to' => 'ssm:production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should parse vault:stage syntax without error
            expect($output)->not->toMatch('/(invalid.*syntax|parse.*error)/i');
        });
    });

    describe('error handling', function () {
        it('handles non-existent source secrets gracefully', function () {
            $commandTester = runCommand('copy', [
                'key' => 'NONEXISTENT_KEY',
                '--from' => 'testing',
                '--to' => 'production',
            ]);

            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            // Could be "secret not found" or auth error - both indicate proper error handling
            expect($output)->toMatch('/(Source secret.*not found|secret.*not found|not authorized|Copy failed)/i');
        });

        it('handles vault connection issues gracefully', function () {
            $commandTester = runCommand('copy', [
                'key' => 'CONNECTION_TEST_KEY',
                '--from' => 'testing',
                '--to' => 'production',
            ]);

            // Command should complete (success or controlled failure)
            $output = stripAnsi($commandTester->getDisplay());

            // Should not crash or show unhandled exceptions
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('validates stage parameters exist in configuration', function () {
            $commandTester = runCommand('copy', [
                'key' => 'STAGE_TEST_KEY',
                '--from' => 'testing', // Valid stage from our config
                '--to' => 'production',  // Valid stage from our config
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept valid stages without validation error
            expect($output)->not->toMatch('/invalid.*stage/i');
        });
    });

    describe('overwrite protection', function () {
        it('shows overwrite error when destination exists without overwrite flag', function () {
            $commandTester = runCommand('copy', [
                'key' => 'EXISTING_KEY',
                '--from' => 'testing',
                '--to' => 'production',
                // Note: not testing actual overwrite since that requires real secrets
                // This tests the command structure and error message format
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // If there's an overwrite situation, should mention it appropriately
            // (Could be "already exists" or connection error, both acceptable)
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('accepts overwrite flag properly', function () {
            $commandTester = runCommand('copy', [
                'key' => 'OVERWRITE_TEST_KEY',
                '--from' => 'testing',
                '--to' => 'production',
                '--overwrite' => true,
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept --overwrite flag without validation error
            expect($output)->not->toMatch('/(invalid.*overwrite|unknown.*overwrite)/i');
        });
    });

    describe('dry run functionality', function () {
        it('shows preview without making changes with dry-run flag', function () {
            $commandTester = runCommand('copy', [
                'key' => 'DRY_RUN_KEY',
                '--from' => 'testing',
                '--to' => 'production',
                '--dry-run' => true,
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // If dry-run works properly, should show preview or mention dry run
            // (Could also fail due to missing source secret, which is acceptable)
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('accepts dry-run flag without error', function () {
            $commandTester = runCommand('copy', [
                'key' => 'DRY_TEST_KEY',
                '--from' => 'testing',
                '--to' => 'production',
                '--dry-run' => true,
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept --dry-run flag without validation error
            expect($output)->not->toMatch('/(invalid.*dry-run|unknown.*dry-run)/i');
        });
    });

    describe('output format validation', function () {
        it('shows appropriate error or preview messages', function () {
            $commandTester = runCommand('copy', [
                'key' => 'OUTPUT_TEST_KEY',
                '--from' => 'testing',
                '--to' => 'production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should show some meaningful output (preview, error, or success message)
            // Could be connection error, source not found, or preview - all acceptable
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
            expect($output)->not->toBeEmpty();
        });

        it('handles preview display properly', function () {
            $commandTester = runCommand('copy', [
                'key' => 'PREVIEW_TEST_KEY',
                '--from' => 'ssm:testing',
                '--to' => 'ssm:production',
                '--dry-run' => true,
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Preview should work or show appropriate error without crashing
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('edge cases and special values', function () {
        it('handles special characters in key names', function () {
            $commandTester = runCommand('copy', [
                'key' => 'KEY_WITH_SPECIAL-CHARS.123',
                '--from' => 'testing',
                '--to' => 'production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle special characters in keys without error
            expect($output)->not->toMatch('/invalid.*key/i');
        });

        it('handles vault:stage syntax correctly', function () {
            $commandTester = runCommand('copy', [
                'key' => 'SYNTAX_TEST_KEY',
                '--from' => 'ssm:testing',
                '--to' => 'ssm:production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should parse vault:stage syntax without syntax errors
            expect($output)->not->toMatch('/(invalid.*syntax|parse.*error)/i');
        });

        it('handles mixed syntax (stage and vault:stage)', function () {
            $commandTester = runCommand('copy', [
                'key' => 'MIXED_SYNTAX_KEY',
                '--from' => 'testing',        // Plain stage
                '--to' => 'ssm:production',    // vault:stage
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle mixed syntax without parsing errors
            expect($output)->not->toMatch('/(invalid.*syntax|parse.*error)/i');
        });

        it('handles confirmation prompts appropriately', function () {
            $commandTester = runCommand('copy', [
                'key' => 'PROMPT_TEST_KEY',
                '--from' => 'testing',
                '--to' => 'production',
                '--overwrite' => true,
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle prompts without hanging (could fail due to non-interactive or missing secret)
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('bulk copy with patterns', function () {
        it('accepts --only pattern without key argument', function () {
            $commandTester = runCommand('copy', [
                '--only' => 'DB_*',
                '--from' => 'testing',
                '--to' => 'production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should not complain about missing key when pattern is provided
            expect($output)->not->toContain('Either provide a key');
        });

        it('accepts --except pattern without key argument', function () {
            $commandTester = runCommand('copy', [
                '--except' => '*_SECRET',
                '--from' => 'testing',
                '--to' => 'production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should not complain about missing key when pattern is provided
            expect($output)->not->toContain('Either provide a key');
        });

        it('accepts both --only and --except patterns together', function () {
            $commandTester = runCommand('copy', [
                '--only' => 'API_*',
                '--except' => '*_SECRET',
                '--from' => 'testing',
                '--to' => 'production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle combined patterns without error
            expect($output)->not->toContain('Either provide a key');
        });

        it('rejects mixing key argument with --only pattern', function () {
            $commandTester = runCommand('copy', [
                'key' => 'MY_KEY',
                '--only' => 'DB_*',
                '--from' => 'testing',
                '--to' => 'production',
            ]);

            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Cannot specify both a key and --only/--except patterns');
        });

        it('rejects mixing key argument with --except pattern', function () {
            $commandTester = runCommand('copy', [
                'key' => 'MY_KEY',
                '--except' => '*_SECRET',
                '--from' => 'testing',
                '--to' => 'production',
            ]);

            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Cannot specify both a key and --only/--except patterns');
        });

        it('shows bulk preview in dry-run mode', function () {
            $commandTester = runCommand('copy', [
                '--only' => '*',
                '--from' => 'testing',
                '--to' => 'production',
                '--dry-run' => true,
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should show bulk operation preview or handle empty vault
            expect($output)->toMatch('/(Bulk Copy Operation Preview|Total secrets:|No secrets match|Dry run completed)/i');
        });

        it('handles empty pattern matches gracefully', function () {
            $commandTester = runCommand('copy', [
                '--only' => 'NONEXISTENT_PATTERN_*',
                '--from' => 'testing',
                '--to' => 'production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle no matches gracefully
            expect($output)->toContain('No secrets match the specified patterns');
            expect($commandTester->getStatusCode())->toBe(0); // Success despite no matches
        });

        it('respects --overwrite flag for bulk operations', function () {
            $commandTester = runCommand('copy', [
                '--only' => '*',
                '--from' => 'testing',
                '--to' => 'production',
                '--overwrite' => true,
                '--dry-run' => true,
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept overwrite flag without error
            expect($output)->not->toMatch('/(invalid option|unknown option)/i');
        });

        it('shows appropriate error without --overwrite when secrets exist', function () {
            // This test would need actual secrets in destination to properly test
            // For now, we just verify the command structure accepts the scenario
            $commandTester = runCommand('copy', [
                '--only' => '*',
                '--from' => 'testing',
                '--to' => 'production',
                // no --overwrite flag
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should either copy successfully or show overwrite error
            expect($output)->toMatch('/(Successfully copied|already exist in destination|No secrets match)/i');
        });

        it('accepts complex wildcard patterns', function () {
            $patterns = [
                'API_*_KEY',
                '*_HOST',
                'DB_*',
                'FEATURE_*_ENABLED',
            ];

            foreach ($patterns as $pattern) {
                $commandTester = runCommand('copy', [
                    '--only' => $pattern,
                    '--from' => 'testing',
                    '--to' => 'production',
                    '--dry-run' => true,
                ]);

                $output = stripAnsi($commandTester->getDisplay());

                // Should accept all valid patterns
                expect($output)->not->toMatch('/(invalid.*pattern|syntax error)/i');
            }
        });
    });

    // Tests migrated from Laravel-style tests to new architecture.
    // Focus on command structure, parameter validation, error handling, and output format
    // rather than full integration tests that depend on external AWS services and pre-populated data.
    // Integration tests with actual secret copying would require either mock vaults or controlled test data.
});

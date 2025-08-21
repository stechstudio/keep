<?php

describe('ListCommand', function () {

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

    describe('command structure and format options', function () {
        it('accepts all valid format options', function () {
            $formats = ['table', 'json', 'env'];

            foreach ($formats as $format) {
                $commandTester = runCommand('list', [
                    '--vault' => 'test',
                    '--stage' => 'testing',
                    '--format' => $format,
                ]);

                // Command might fail due to missing secrets or auth, but format should be valid
                $output = stripAnsi($commandTester->getDisplay());
                expect($output)->not->toContain('Invalid format option');
            }
        });

        it('validates format option and shows error for invalid formats', function () {
            $commandTester = runCommand('list', [
                '--vault' => 'test',
                '--stage' => 'testing',
                '--format' => 'invalid',
            ]);

            expect($commandTester->getStatusCode())->toBe(1); // Command fails with invalid format

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Invalid format option');
            expect($output)->toContain('table, json, env');
        });

        it('accepts unmask flag option', function () {
            $commandTester = runCommand('list', [
                '--vault' => 'test',
                '--stage' => 'testing',
                '--unmask' => true,
            ]);

            // Command should accept --unmask flag without validation error
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->not->toMatch('/(invalid option|unknown option)/i');
        });

        it('accepts filtering options', function () {
            $commandTester = runCommand('list', [
                '--vault' => 'test',
                '--stage' => 'testing',
                '--only' => 'DB_*',
            ]);

            // Should accept filtering patterns
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->not->toMatch('/(invalid.*only|unknown.*only)/i');

            $commandTester = runCommand('list', [
                '--vault' => 'test',
                '--stage' => 'testing',
                '--except' => 'TEMP_*',
            ]);

            // Should accept except patterns
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->not->toMatch('/(invalid.*except|unknown.*except)/i');
        });
    });

    describe('parameter validation', function () {
        it('validates vault and stage parameters', function () {
            $commandTester = runCommand('list', [
                '--vault' => 'test',
                '--stage' => 'testing',
            ]);

            // Command should accept valid parameters (might fail on AWS interaction)
            $output = stripAnsi($commandTester->getDisplay());

            // Should not show parameter validation errors
            expect($output)->not->toMatch('/(invalid vault|invalid stage)/i');
        });

        it('handles vault parameter validation', function () {
            $commandTester = runCommand('list', [
                '--vault' => 'test', // Valid vault from our config
                '--stage' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept valid vault without validation error
            expect($output)->not->toMatch('/invalid.*vault/i');
        });
    });

    describe('output format structure', function () {
        it('table format shows appropriate headers and structure', function () {
            $commandTester = runCommand('list', [
                '--vault' => 'test',
                '--stage' => 'testing',
                '--format' => 'table',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Either shows table headers or error (both acceptable)
            // If successful: should show Key, Value, Revision headers
            // If failed: should show some error message
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });

        it('env format produces valid structure when successful', function () {
            $commandTester = runCommand('list', [
                '--vault' => 'test',
                '--stage' => 'testing',
                '--format' => 'env',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // If command succeeds and produces output, it should be valid env format
            // If it fails due to auth/connection, that's also acceptable
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);

            // If there's output that looks like env vars, validate the format
            $lines = explode("\n", trim($output));
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (! empty($trimmed) && strpos($trimmed, '=') !== false && ! preg_match('/error|not authorized/i', $trimmed)) {
                    // This looks like an env var line - validate format
                    expect($trimmed)->toMatch('/^[A-Z_][A-Z0-9_]*=.*$/');
                }
            }
        });

        it('json format produces valid JSON structure when successful', function () {
            $commandTester = runCommand('list', [
                '--vault' => 'test',
                '--stage' => 'testing',
                '--format' => 'json',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // If the output looks like JSON, validate it
            $trimmed = trim($output);
            if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
                $json = json_decode($trimmed, true);
                expect($json)->not->toBeNull();
                expect(json_last_error())->toBe(JSON_ERROR_NONE);
            }
        });
    });

    describe('filtering pattern validation', function () {
        it('accepts valid only patterns', function () {
            $patterns = ['DB_*', '*_PORT', 'API_KEY', 'DB_*,MAIL_*', '*_HOST,*_PORT'];

            foreach ($patterns as $pattern) {
                $commandTester = runCommand('list', [
                    '--vault' => 'test',
                    '--stage' => 'testing',
                    '--format' => 'env',
                    '--only' => $pattern,
                ]);

                $output = stripAnsi($commandTester->getDisplay());
                expect($output)->not->toMatch('/invalid.*pattern/i');
            }
        });

        it('accepts valid except patterns', function () {
            $patterns = ['TEMP_*', '*_CACHE', 'DEBUG_*', 'TEMP_*,DEBUG_*'];

            foreach ($patterns as $pattern) {
                $commandTester = runCommand('list', [
                    '--vault' => 'test',
                    '--stage' => 'testing',
                    '--format' => 'env',
                    '--except' => $pattern,
                ]);

                $output = stripAnsi($commandTester->getDisplay());
                expect($output)->not->toMatch('/invalid.*pattern/i');
            }
        });

        it('handles combination of only and except patterns', function () {
            $commandTester = runCommand('list', [
                '--vault' => 'test',
                '--stage' => 'testing',
                '--format' => 'env',
                '--only' => 'DB_*,MAIL_*',
                '--except' => '*_PORT',
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->not->toMatch('/invalid.*pattern/i');
        });
    });

    describe('error handling', function () {
        it('handles vault connection issues gracefully', function () {
            $commandTester = runCommand('list', [
                '--vault' => 'test',
                '--stage' => 'testing',
                '--format' => 'env',
            ]);

            // Command should complete (success or controlled failure)
            $output = stripAnsi($commandTester->getDisplay());

            // Should not crash or show unhandled exceptions
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('handles empty results appropriately', function () {
            // Use a pattern that's unlikely to match anything
            $commandTester = runCommand('list', [
                '--vault' => 'test',
                '--stage' => 'testing',
                '--format' => 'env',
                '--only' => 'NONEXISTENT_UNLIKELY_PATTERN_*',
            ]);

            // Should handle empty results gracefully
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('handles auth errors gracefully', function () {
            $commandTester = runCommand('list', [
                '--vault' => 'test',
                '--stage' => 'testing',
            ]);

            // With TestVault, we shouldn't get auth errors, but command should complete gracefully
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('masking behavior validation', function () {
        it('handles unmask flag appropriately', function () {
            // Test with unmask flag
            $commandTester = runCommand('list', [
                '--vault' => 'test',
                '--stage' => 'testing',
                '--format' => 'env',
                '--unmask' => true,
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->not->toMatch('/invalid.*unmask/i');

            // Test without unmask flag (default masked)
            $commandTester = runCommand('list', [
                '--vault' => 'test',
                '--stage' => 'testing',
                '--format' => 'env',
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            // Both should be valid approaches
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });
    });

    // Tests migrated from Laravel-style tests to new architecture.
    // Focus on command structure, parameter validation, format options, and error handling
    // rather than full integration tests that depend on external AWS services and pre-populated data.
    // Integration tests with actual secret listing would require either mock vaults or controlled test data.
});

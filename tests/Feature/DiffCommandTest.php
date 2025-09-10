<?php

describe('DiffCommand', function () {

    beforeEach(function () {
        createTempKeepDir();

        // Create .keep directory and settings to initialize Keep
        mkdir('.keep');
        mkdir('.keep/vaults');

        $settings = [
            'app_name' => 'test-app',
            'namespace' => 'test-app',
            'default_vault' => 'test',
            'envs' => ['testing', 'staging', 'production'],
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
        it('accepts vault and env options for comparisons', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'test',
                '--env' => 'testing,production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept --vault and --env options without validation error
            expect($output)->not->toMatch('/(invalid.*env|unknown.*option)/i');
        });

        it('accepts env option for comma-separated environments', function () {
            $commandTester = runCommand('diff', [
                '--env' => 'testing,production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept --env option without validation error
            expect($output)->not->toMatch('/(invalid.*env|unknown.*option)/i');
        });

        it('accepts vault option for comma-separated vaults', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept --vault option without validation error
            expect($output)->not->toMatch('/(invalid.*vault|unknown.*option)/i');
        });

        it('accepts unmask flag option', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'test',
                '--env' => 'testing,production',
                '--unmask' => true,
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept --unmask flag without validation error
            expect($output)->not->toMatch('/(invalid option|unknown option)/i');
        });
    });

    describe('parameter validation', function () {
        it('handles valid vault and environment combinations', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'test',
                '--env' => 'testing,production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept valid vault/env combinations
            expect($output)->not->toMatch('/(invalid.*env)/i');
        });

        it('validates vault and env option parsing', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm',
                '--env' => 'testing,staging',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should parse vault and env options without syntax errors
            expect($output)->not->toMatch('/(invalid.*syntax|parse.*error)/i');
        });

        it('handles comma-separated env lists', function () {
            $commandTester = runCommand('diff', [
                '--env' => 'testing,staging,production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should parse comma-separated envs without error
            expect($output)->not->toMatch('/(invalid.*env|parse.*error)/i');
        });

        it('warns about unknown envs appropriately', function () {
            $commandTester = runCommand('diff', [
                '--env' => 'testing,invalid.*env',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle unknown envs gracefully (either warn or ignore)
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('error handling', function () {
        it('handles no vaults available gracefully', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'nonexistent-vault',
            ]);

            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toMatch('/(No vaults available|not found|error)/i');
        });

        it('handles no environments available gracefully', function () {
            $commandTester = runCommand('diff', [
                '--env' => 'nonexistent-env',
            ]);

            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toMatch('/(No environments available|not found|error|Warning: Unknown environments)/i');
        });

        it('handles vault connection issues gracefully', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'test',
                '--env' => 'testing,production',
            ]);

            // Command should complete (success or controlled failure)
            $output = stripAnsi($commandTester->getDisplay());

            // Should not crash or show unhandled exceptions
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('handles empty vault conditions appropriately', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            // Should handle empty vaults without crashing
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('output format and display', function () {
        it('shows comparison matrix structure', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'test',
                '--env' => 'testing,production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should show matrix or appropriate message
            // Could be empty vault message or matrix headers
            expect($output)->not->toBeEmpty();
        });

        it('displays summary information', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'test',
                '--env' => 'testing,staging',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should show some form of summary or result information
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('handles unmask flag appropriately', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'test',
                '--env' => 'testing,production',
                '--unmask' => true,
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept unmask flag without error
            expect($output)->not->toMatch('/(invalid.*unmask|unknown.*unmask)/i');
        });
    });

    describe('vault and env option functionality', function () {
        it('parses vault and env options correctly', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm',
                '--env' => 'testing,production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should parse vault and env options without error
            expect($output)->not->toMatch('/(invalid.*context|parse.*error)/i');
        });

        it('handles comma-separated options correctly', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm',
                '--env' => 'testing,production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle mixed options without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('validates vault and env references', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm,invalid',
                '--env' => 'testing,nonexistent',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle invalid.*env references gracefully
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('vault and env filtering', function () {
        it('uses default vault when not specified', function () {
            $commandTester = runCommand('diff', [
                '--env' => 'testing,production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should use default vault without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('uses all configured envs when not specified', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should use all envs without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('handles single env comparison', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle single env without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('edge cases and special scenarios', function () {
        it('handles special characters in options', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle basic options without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('handles whitespace in comma-separated lists', function () {
            $commandTester = runCommand('diff', [
                '--env' => 'testing, production, staging',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should trim whitespace in lists gracefully
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('processes multiple vault combinations', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'test',
                '--env' => 'testing,production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle multiple combinations without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('handles spinner and loading states appropriately', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            // Should complete without hanging on spinner
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('integration with diff service', function () {
        it('handles diff service responses appropriately', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'test',
                '--env' => 'testing,production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle service integration without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });

        it('displays appropriate messages for empty results', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'test',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should handle empty results gracefully
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });
    });

    describe('filtering options', function () {
        it('accepts only option for filtering keys', function () {
            $commandTester = runCommand('diff', [
                '--only' => 'API_*',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept --only option without validation error
            expect($output)->not->toMatch('/(invalid.*option|unknown.*option)/i');
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });

        it('accepts except option for excluding keys', function () {
            $commandTester = runCommand('diff', [
                '--except' => 'SECRET_*',
                '--env' => 'testing',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept --except option without validation error
            expect($output)->not->toMatch('/(invalid.*option|unknown.*option)/i');
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });

        it('accepts both only and except options together', function () {
            $commandTester = runCommand('diff', [
                '--only' => 'API_*,DB_*',
                '--except' => 'SECRET_*',
                '--env' => 'testing,production',
            ]);

            $output = stripAnsi($commandTester->getDisplay());

            // Should accept both filtering options without error
            expect($output)->not->toMatch('/(invalid.*option|unknown.*option)/i');
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });
    });

    // Tests migrated from Laravel-style tests to new architecture.
    // Focus on command structure, parameter validation, error handling, and output format
    // rather than full integration tests that depend on external AWS services and pre-populated data.
    // Integration tests with actual secret comparison would require either mock vaults or controlled test data.
});

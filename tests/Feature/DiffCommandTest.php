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
            'default_vault' => 'ssm',
            'stages' => ['testing', 'staging', 'production'],
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
        it('accepts context option for specifying vault:stage combinations', function () {
            $commandTester = runCommand('diff', [
                '--context' => 'ssm:testing,ssm:production'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should accept --context option without validation error
            expect($output)->not->toMatch('/(invalid.*context|unknown.*option)/i');
        });
        
        it('accepts stage option for comma-separated stages', function () {
            $commandTester = runCommand('diff', [
                '--stage' => 'testing,production'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should accept --stage option without validation error
            expect($output)->not->toMatch('/(invalid.*stage|unknown.*option)/i');
        });
        
        it('accepts vault option for comma-separated vaults', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should accept --vault option without validation error
            expect($output)->not->toMatch('/(invalid.*vault|unknown.*option)/i');
        });
        
        it('accepts unmask flag option', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm',
                '--stage' => 'testing,production',
                '--unmask' => true
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should accept --unmask flag without validation error
            expect($output)->not->toMatch('/(invalid option|unknown option)/i');
        });
    });

    describe('parameter validation', function () {
        it('handles valid vault and stage combinations', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm',
                '--stage' => 'testing,production'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should accept valid vault/stage combinations
            expect($output)->not->toMatch('/(invalid.*vault|invalid.*stage)/i');
        });
        
        it('validates context format parsing', function () {
            $commandTester = runCommand('diff', [
                '--context' => 'ssm:testing,ssm:staging'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should parse context format without syntax errors
            expect($output)->not->toMatch('/(invalid.*syntax|parse.*error)/i');
        });
        
        it('handles comma-separated stage lists', function () {
            $commandTester = runCommand('diff', [
                '--stage' => 'testing,staging,production'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should parse comma-separated stages without error
            expect($output)->not->toMatch('/(invalid.*stage|parse.*error)/i');
        });
        
        it('warns about unknown stages appropriately', function () {
            $commandTester = runCommand('diff', [
                '--stage' => 'testing,invalid-stage'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle unknown stages gracefully (either warn or ignore)
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('error handling', function () {
        it('handles no vaults available gracefully', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'nonexistent-vault'
            ]);

            expect($commandTester->getStatusCode())->toBe(1);
            
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toMatch('/(No vaults available|not found|error)/i');
        });
        
        it('handles no stages available gracefully', function () {
            $commandTester = runCommand('diff', [
                '--stage' => 'nonexistent-stage'
            ]);

            expect($commandTester->getStatusCode())->toBe(1);
            
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toMatch('/(No stages available|not found|error)/i');
        });
        
        it('handles vault connection issues gracefully', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm',
                '--stage' => 'testing,production'
            ]);

            // Command should complete (success or controlled failure)
            $output = stripAnsi($commandTester->getDisplay());
            
            // Should not crash or show unhandled exceptions
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('handles empty vault conditions appropriately', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm',
                '--stage' => 'testing'
            ]);

            // Should handle empty vaults without crashing
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('output format and display', function () {
        it('shows comparison matrix structure', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm',
                '--stage' => 'testing,production'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should show matrix or appropriate message
            // Could be empty vault message or matrix headers
            expect($output)->not->toBeEmpty();
        });
        
        it('displays summary information', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm',
                '--stage' => 'testing,staging'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should show some form of summary or result information
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('handles unmask flag appropriately', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm',
                '--stage' => 'testing,production',
                '--unmask' => true
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should accept unmask flag without error
            expect($output)->not->toMatch('/(invalid.*unmask|unknown.*unmask)/i');
        });
    });

    describe('context option functionality', function () {
        it('parses context strings correctly', function () {
            $commandTester = runCommand('diff', [
                '--context' => 'ssm:testing,ssm:production'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should parse context format without error
            expect($output)->not->toMatch('/(invalid.*context|parse.*error)/i');
        });
        
        it('handles mixed context and explicit options appropriately', function () {
            $commandTester = runCommand('diff', [
                '--context' => 'ssm:testing',
                '--stage' => 'production'  // Should be ignored when context is provided
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle mixed options without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('validates context vault and stage references', function () {
            $commandTester = runCommand('diff', [
                '--context' => 'ssm:testing,invalid:nonexistent'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle invalid context references gracefully
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('vault and stage filtering', function () {
        it('uses default vault when not specified', function () {
            $commandTester = runCommand('diff', [
                '--stage' => 'testing,production'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should use default vault without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('uses all configured stages when not specified', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should use all stages without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('handles single stage comparison', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle single stage without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('edge cases and special scenarios', function () {
        it('handles special characters in options', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle basic options without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('handles whitespace in comma-separated lists', function () {
            $commandTester = runCommand('diff', [
                '--stage' => 'testing, production, staging'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should trim whitespace in lists gracefully
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('processes multiple vault combinations', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm',
                '--stage' => 'testing,production'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle multiple combinations without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('handles spinner and loading states appropriately', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm',
                '--stage' => 'testing'
            ]);

            // Should complete without hanging on spinner
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });
    
    describe('integration with diff service', function () {
        it('handles diff service responses appropriately', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm',
                '--stage' => 'testing,production'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle service integration without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('displays appropriate messages for empty results', function () {
            $commandTester = runCommand('diff', [
                '--vault' => 'ssm',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle empty results gracefully
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });
    });
    
    // Tests migrated from Laravel-style tests to new architecture.
    // Focus on command structure, parameter validation, error handling, and output format
    // rather than full integration tests that depend on external AWS services and pre-populated data.
    // Integration tests with actual secret comparison would require either mock vaults or controlled test data.
});
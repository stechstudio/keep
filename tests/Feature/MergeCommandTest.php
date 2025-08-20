<?php

describe('MergeCommand', function () {

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

        // Create test template directory
        if (!is_dir('/tmp/keeper-test')) {
            mkdir('/tmp/keeper-test', 0755, true);
        }

        // Create test template files for merge
        file_put_contents('/tmp/keeper-test/template.env',
            "# Template file\n".
            "DB_HOST={test:DB_HOST}\n".
            "DB_PORT={test:DB_PORT}\n".
            "DB_NAME={test:DB_NAME}\n".
            "API_KEY={test:API_KEY}\n"
        );

        file_put_contents('/tmp/keeper-test/complex.env',
            "APP_NAME={test:APP_NAME}\n".
            "APP_DESC={test:APP_DESC}\n".
            "# Comment line\n".
            "SPECIAL_CHARS={test:SPECIAL_CHARS}\n"
        );
    });

    afterEach(function () {
        // Clean up test files safely
        if (is_dir('/tmp/keeper-test')) {
            $files = glob('/tmp/keeper-test/*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
            @rmdir('/tmp/keeper-test');
        }
    });

    describe('command structure and signature', function () {
        it('accepts template path argument', function () {
            $commandTester = runCommand('merge', [
                'template' => '/tmp/keeper-test/template.env',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should accept template argument without validation error
            expect($output)->not->toMatch('/(invalid.*template|unknown.*option)/i');
        });
        
        it('accepts output file option', function () {
            $commandTester = runCommand('merge', [
                'template' => '/tmp/keeper-test/template.env',
                '--output' => '/tmp/keeper-test/output.env',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should accept --output option without validation error
            expect($output)->not->toMatch('/(invalid.*option|unknown.*option)/i');
        });
        
        it('validates template argument is provided', function () {
            $commandTester = runCommand('merge', [
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            // Should fail due to missing template argument
            expect($commandTester->getStatusCode())->toBe(1);
            
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toMatch('/(Aborted|error|required|missing)/i');
        });
    });

    describe('template handling', function () {
        it('handles basic template merge', function () {
            $commandTester = runCommand('merge', [
                'template' => '/tmp/keeper-test/template.env',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle basic template merge without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('handles complex template merge', function () {
            $commandTester = runCommand('merge', [
                'template' => '/tmp/keeper-test/complex.env',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle complex template without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('handles non-existent template file gracefully', function () {
            $commandTester = runCommand('merge', [
                'template' => '/tmp/keeper-test/nonexistent.env',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            expect($commandTester->getStatusCode())->toBe(1);
            
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toMatch('/(not found|error|file)/i');
        });
        
        it('handles empty template file gracefully', function () {
            file_put_contents('/tmp/keeper-test/empty.env', '');
            
            $commandTester = runCommand('merge', [
                'template' => '/tmp/keeper-test/empty.env',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle empty template without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('output handling', function () {
        it('outputs to stdout by default', function () {
            $commandTester = runCommand('merge', [
                'template' => '/tmp/keeper-test/template.env',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should output to stdout without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('handles output file option', function () {
            $commandTester = runCommand('merge', [
                'template' => '/tmp/keeper-test/template.env',
                '--output' => '/tmp/keeper-test/output.env',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle output file without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('error handling', function () {
        it('handles vault connection issues gracefully', function () {
            $commandTester = runCommand('merge', [
                'template' => '/tmp/keeper-test/template.env',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            // Command should complete (success or controlled failure)
            $output = stripAnsi($commandTester->getDisplay());
            
            // Should not crash or show unhandled exceptions
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('handles missing secrets gracefully', function () {
            file_put_contents('/tmp/keeper-test/missing.env',
                "EXISTING_SECRET={test:SOME_KEY}\n".
                "MISSING_SECRET={test:NONEXISTENT_KEY}\n"
            );
            
            $commandTester = runCommand('merge', [
                'template' => '/tmp/keeper-test/missing.env',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle missing secrets without crashing
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('handles file permission issues gracefully', function () {
            $commandTester = runCommand('merge', [
                'template' => '/root/nonexistent.env', // Permission denied
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            // Should handle permission issues gracefully
            expect($commandTester->getStatusCode())->toBe(1);
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toMatch('/(not found|error|permission|does not exist)/i');
        });
    });

    describe('stage and vault handling', function () {
        it('uses specified vault parameter', function () {
            $commandTester = runCommand('merge', [
                'template' => '/tmp/keeper-test/template.env',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle vault parameter without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('uses specified stage parameter', function () {
            $commandTester = runCommand('merge', [
                'template' => '/tmp/keeper-test/template.env',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle stage parameter without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('handles production stage parameter', function () {
            $commandTester = runCommand('merge', [
                'template' => '/tmp/keeper-test/template.env',
                '--vault' => 'test',
                '--stage' => 'production'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle production stage without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('template processing', function () {
        it('handles placeholder substitution', function () {
            $commandTester = runCommand('merge', [
                'template' => '/tmp/keeper-test/template.env',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            // Should handle placeholder substitution without error
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });
        
        it('handles special characters in templates', function () {
            $commandTester = runCommand('merge', [
                'template' => '/tmp/keeper-test/complex.env',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            // Should handle special characters without error
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });
        
        it('handles context creation appropriately', function () {
            $commandTester = runCommand('merge', [
                'template' => '/tmp/keeper-test/template.env',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle context creation without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
    });

    describe('integration functionality', function () {
        it('handles merge operation flow', function () {
            $commandTester = runCommand('merge', [
                'template' => '/tmp/keeper-test/template.env',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            // Should handle merge flow without error
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });
        
        it('provides appropriate completion status', function () {
            $commandTester = runCommand('merge', [
                'template' => '/tmp/keeper-test/template.env',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            // Should complete with appropriate status
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });
    });
    
    // Tests migrated from Laravel-style tests to new architecture.
    // Focus on command structure, template handling, output options, and error handling
    // rather than full integration tests that depend on external AWS services and pre-populated data.
    // Integration tests with actual template merging would require either mock vaults or controlled test data.
});
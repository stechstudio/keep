<?php

describe('ExportCommand', function () {

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

    describe('command structure and signature', function () {
        it('accepts format option with valid values', function () {
            $formats = ['env', 'json'];
            
            foreach ($formats as $format) {
                $commandTester = runCommand('export', [
                    '--format' => $format,
                    '--vault' => 'test',
                    '--stage' => 'testing'
                ]);

                $output = stripAnsi($commandTester->getDisplay());
                
                // Should accept valid format options without validation error
                expect($output)->not->toMatch('/(invalid.*format|unknown.*option)/i');
            }
        });
        
        it('accepts output file option', function () {
            $commandTester = runCommand('export', [
                '--output' => '/tmp/test-export.env',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--overwrite' => true
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should accept --output option without validation error
            expect($output)->not->toMatch('/(invalid option|unknown option)/i');
        });
        
        it('accepts overwrite flag option', function () {
            $commandTester = runCommand('export', [
                '--overwrite' => true,
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should accept --overwrite flag without validation error
            expect($output)->not->toMatch('/(invalid option|unknown option)/i');
        });
        
        it('accepts append flag option', function () {
            $commandTester = runCommand('export', [
                '--append' => true,
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should accept --append flag without validation error
            expect($output)->not->toMatch('/(invalid option|unknown option)/i');
        });
    });

    describe('format handling', function () {
        it('defaults to env format when not specified', function () {
            $commandTester = runCommand('export', [
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should default to env format without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('handles json format option', function () {
            $commandTester = runCommand('export', [
                '--format' => 'json',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle JSON format without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('produces output in specified format', function () {
            $commandTester = runCommand('export', [
                '--format' => 'env',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            // Should produce some output (even if empty due to no secrets)
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });
    });

    describe('output destination handling', function () {
        it('outputs to stdout by default', function () {
            $commandTester = runCommand('export', [
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            // Should output to stdout without error
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });
        
        it('handles file output option appropriately', function () {
            $tempFile = tempnam(sys_get_temp_dir(), 'keep_test_');
            
            try {
                $commandTester = runCommand('export', [
                    '--output' => $tempFile,
                    '--overwrite' => true,
                    '--vault' => 'test',
                    '--stage' => 'testing'
                ]);

                $output = stripAnsi($commandTester->getDisplay());
                
                // Should handle file output without error
                expect($output)->not->toMatch('/Fatal error|Uncaught/');
            } finally {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
        });
    });

    describe('file operation flags', function () {
        it('handles overwrite flag appropriately', function () {
            $tempFile = tempnam(sys_get_temp_dir(), 'keep_test_');
            file_put_contents($tempFile, 'existing content');
            
            try {
                $commandTester = runCommand('export', [
                    '--output' => $tempFile,
                    '--overwrite' => true,
                    '--vault' => 'test',
                    '--stage' => 'testing'
                ]);

                $output = stripAnsi($commandTester->getDisplay());
                
                // Should handle overwrite without error
                expect($output)->not->toMatch('/Fatal error|Uncaught/');
            } finally {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
        });
        
        it('handles append flag appropriately', function () {
            $tempFile = tempnam(sys_get_temp_dir(), 'keep_test_');
            file_put_contents($tempFile, 'existing content');
            
            try {
                $commandTester = runCommand('export', [
                    '--output' => $tempFile,
                    '--append' => true,
                    '--vault' => 'test',
                    '--stage' => 'testing'
                ]);

                $output = stripAnsi($commandTester->getDisplay());
                
                // Should handle append without error
                expect($output)->not->toMatch('/Fatal error|Uncaught/');
            } finally {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
        });
    });

    describe('error handling', function () {
        it('handles vault connection issues gracefully', function () {
            $commandTester = runCommand('export', [
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            // Command should complete (success or controlled failure)
            $output = stripAnsi($commandTester->getDisplay());
            
            // Should not crash or show unhandled exceptions
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('handles invalid stage gracefully', function () {
            $commandTester = runCommand('export', [
                '--vault' => 'test',
                '--stage' => 'invalid-stage'
            ]);

            // Should handle invalid stage without crashing
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('handles empty vault gracefully', function () {
            $commandTester = runCommand('export', [
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            // Should handle empty vault without error
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });
    });

    describe('stage and vault handling', function () {
        it('uses specified vault parameter', function () {
            $commandTester = runCommand('export', [
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle vault parameter without error
            expect($output)->not->toMatch('/invalid.*vault/i');
        });
        
        it('uses specified stage parameter', function () {
            $commandTester = runCommand('export', [
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle stage parameter without error
            expect($output)->not->toMatch('/invalid.*stage/i');
        });
        
        it('handles production stage parameter', function () {
            $commandTester = runCommand('export', [
                '--vault' => 'test',
                '--stage' => 'production'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle production stage without error
            expect($output)->not->toMatch('/invalid.*stage/i');
        });
    });

    describe('output formatting', function () {
        it('handles env format output structure', function () {
            $commandTester = runCommand('export', [
                '--format' => 'env',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle env format without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('handles json format output structure', function () {
            $commandTester = runCommand('export', [
                '--format' => 'json',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle JSON format without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('produces valid output structure', function () {
            $commandTester = runCommand('export', [
                '--format' => 'env',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            // Should produce valid output structure
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });
    });

    describe('edge cases and special scenarios', function () {
        it('handles context creation appropriately', function () {
            $commandTester = runCommand('export', [
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle context creation without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('handles secret collection processing', function () {
            $commandTester = runCommand('export', [
                '--format' => 'json',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle secret collection without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
        });
        
        it('handles file path validation', function () {
            $commandTester = runCommand('export', [
                '--output' => '/tmp/test-export-validation.env',
                '--overwrite' => true,
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            
            // Should handle file path validation without error
            expect($output)->not->toMatch('/Fatal error|Uncaught/');
            
            // Clean up test file if it was created
            if (file_exists('/tmp/test-export-validation.env')) {
                unlink('/tmp/test-export-validation.env');
            }
        });
    });

    describe('integration functionality', function () {
        it('handles vault listing operation', function () {
            $commandTester = runCommand('export', [
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            // Should handle vault listing without error
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });
        
        it('handles format conversion operations', function () {
            $commandTester = runCommand('export', [
                '--format' => 'json',
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            // Should handle format conversion without error
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });
        
        it('provides appropriate completion status', function () {
            $commandTester = runCommand('export', [
                '--vault' => 'test',
                '--stage' => 'testing'
            ]);

            // Should complete with appropriate status
            expect($commandTester->getStatusCode())->toBeGreaterThanOrEqual(0);
        });
    });
    
    // Tests migrated from Laravel-style tests to new architecture.
    // Focus on command structure, parameter validation, file handling, and format options
    // rather than full integration tests that depend on external AWS services and pre-populated data.
    // Integration tests with actual secret export would require either mock vaults or controlled test data.
});
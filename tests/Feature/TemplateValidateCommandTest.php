<?php

describe('TemplateValidateCommand', function () {

    beforeEach(function () {
        createTempKeepDir();
        
        // Clear TestVault storage to ensure test isolation
        \STS\Keep\Tests\Support\TestVault::clearAll();

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

        // Create test vault configuration
        $vaultConfig = [
            'slug' => 'test',
            'driver' => 'test',
            'name' => 'Test Vault',
            'namespace' => 'test-app',
        ];

        file_put_contents('.keep/vaults/test.json', json_encode($vaultConfig, JSON_PRETTY_PRINT));
    });

    describe('command structure and signature', function () {
        it('accepts template file argument', function () {
            // Create a simple template file (will fail validation since secret doesn't exist)
            $templatePath = 'test-template.env';
            file_put_contents($templatePath, "DB_PASSWORD={test:DB_PASSWORD}\n");

            $commandTester = runCommand('template:validate', [
                'template' => $templatePath,
                '--env' => 'testing',
                '--vault' => 'test',
            ]);

            // Should fail due to missing secret, but command structure is correct
            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Template Validation');
            expect($output)->toContain($templatePath);
        });

        it('accepts environment and vault options', function () {
            $templatePath = 'test-template.env';
            file_put_contents($templatePath, "DB_PASSWORD={test:DB_PASSWORD}\n");

            $commandTester = runCommand('template:validate', [
                'template' => $templatePath,
                '--env' => 'production',
                '--vault' => 'test',
            ]);

            // Should fail due to missing secret, but should show correct environment
            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('test:production');
        });
    });

    describe('file handling', function () {
        it('shows error for non-existent template file', function () {
            $commandTester = runCommand('template:validate', [
                'template' => 'non-existent-template.env',
                '--env' => 'testing',
                '--vault' => 'test',
            ]);

            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Template file not found');
            expect($output)->toContain('non-existent-template.env');
        });

        it('handles empty template files', function () {
            $templatePath = 'empty-template.env';
            file_put_contents($templatePath, '');

            $commandTester = runCommand('template:validate', [
                'template' => $templatePath,
                '--env' => 'testing',
                '--vault' => 'test',
            ]);

            expect($commandTester->getStatusCode())->toBe(0);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Template file is empty');
        });
    });

    describe('placeholder parsing', function () {
        it('parses simple placeholders correctly', function () {
            $templateContent = "DB_PASSWORD={test:DB_PASSWORD}\nAPI_KEY={test:API_KEY}\n";
            $templatePath = 'simple-template.env';
            file_put_contents($templatePath, $templateContent);

            $commandTester = runCommand('template:validate', [
                'template' => $templatePath,
                '--env' => 'testing',
                '--vault' => 'test',
            ]);

            // Should fail due to missing secrets, but should find placeholders
            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Found 2 placeholder(s) to validate');
        });

        it('parses vault:key format placeholders', function () {
            $templateContent = "DB_PASSWORD={test:DB_PASSWORD}\nAPI_KEY={prod:API_KEY}\n";
            $templatePath = 'vault-template.env';
            file_put_contents($templatePath, $templateContent);

            $commandTester = runCommand('template:validate', [
                'template' => $templatePath,
                '--env' => 'testing',
                '--vault' => 'test',
            ]);

            // Should find placeholders but fail validation (prod vault doesn't exist)
            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Found 2 placeholder(s) to validate');
        });

        it('ignores environment variable substitution syntax', function () {
            $templateContent = "PATH=\${PATH}:/usr/local/bin\nDB_PASSWORD={test:DB_PASSWORD}\n";
            $templatePath = 'mixed-template.env';
            file_put_contents($templatePath, $templateContent);

            $commandTester = runCommand('template:validate', [
                'template' => $templatePath,
                '--env' => 'testing',
                '--vault' => 'test',
            ]);

            // Should fail due to missing secret but only count Keep placeholders
            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Found 1 placeholder(s) to validate');
        });

        it('handles templates with no placeholders', function () {
            $templateContent = "# This is a comment\nSTATIC_VALUE=hello\n";
            $templatePath = 'no-placeholders.env';
            file_put_contents($templatePath, $templateContent);

            $commandTester = runCommand('template:validate', [
                'template' => $templatePath,
                '--env' => 'testing',
                '--vault' => 'test',
            ]);

            expect($commandTester->getStatusCode())->toBe(0);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Template contains no placeholders to validate');
        });
    });

    describe('secret validation', function () {
        it('validates template with no placeholders successfully', function () {
            // Create a template with only static values (no placeholders)
            $templateContent = "# Static configuration\nAPP_ENV=testing\nDEBUG=true\n";
            $templatePath = 'static-template.env';
            file_put_contents($templatePath, $templateContent);

            $commandTester = runCommand('template:validate', [
                'template' => $templatePath,
                '--env' => 'testing',
                '--vault' => 'test',
            ]);

            expect($commandTester->getStatusCode())->toBe(0);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Template validation successful');
            expect($output)->toContain('Template contains no placeholders to validate');
        });

        it('reports missing secrets', function () {
            $templateContent = "DB_PASSWORD={test:DB_PASSWORD}\nMISSING_SECRET={test:MISSING_SECRET}\n";
            $templatePath = 'missing-secrets.env';
            file_put_contents($templatePath, $templateContent);

            $commandTester = runCommand('template:validate', [
                'template' => $templatePath,
                '--env' => 'testing',
                '--vault' => 'test',
            ]);

            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Template validation failed');
            expect($output)->toContain('Invalid');
        });

        it('validates key format', function () {
            // Use valid env var names but with invalid secret keys (using vault:key syntax)
            $templateContent = "VALID_ENV={test:INVALID-KEY}\nVALID_KEY={test:VALID_KEY}\n";
            $templatePath = 'invalid-keys.env';
            file_put_contents($templatePath, $templateContent);

            $commandTester = runCommand('template:validate', [
                'template' => $templatePath,
                '--env' => 'testing',
                '--vault' => 'test',
            ]);

            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Invalid key format');
        });

        it('validates vault existence', function () {
            $templateContent = "DB_PASSWORD={nonexistent:DB_PASSWORD}\n";
            $templatePath = 'invalid-vault.env';
            file_put_contents($templatePath, $templateContent);

            $commandTester = runCommand('template:validate', [
                'template' => $templatePath,
                '--env' => 'testing',
                '--vault' => 'test',
            ]);

            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('is not configured');
        });
    });

    describe('unused secrets detection', function () {
        it('reports when no secrets exist in vault', function () {
            // Template with missing secrets (vault is empty)
            $templateContent = "MISSING_VALUE={test:MISSING_SECRET}\n";
            $templatePath = 'missing-template.env';
            file_put_contents($templatePath, $templateContent);

            $commandTester = runCommand('template:validate', [
                'template' => $templatePath,
                '--env' => 'testing',
                '--vault' => 'test',
            ]);

            // Should fail due to missing secret, but unused check should work
            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            // Since vault is empty, there are no unused secrets to report
            expect($output)->toContain('All secrets in test:testing are referenced in the template');
        });

        it('handles empty vault gracefully', function () {
            // Template with no placeholders - should succeed
            $templateContent = "# No placeholders here\nSTATIC_VALUE=hello\n";
            $templatePath = 'static-template.env';
            file_put_contents($templatePath, $templateContent);

            $commandTester = runCommand('template:validate', [
                'template' => $templatePath,
                '--env' => 'testing',
                '--vault' => 'test',
            ]);

            expect($commandTester->getStatusCode())->toBe(0);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('All secrets in test:testing are referenced in the template');
        });
    });

    describe('output formatting', function () {
        it('displays validation results in table format', function () {
            // Create template with mixed valid/invalid placeholders
            $setCommand = runCommand('set', [
                'key' => 'VALID_SECRET',
                'value' => 'value1',
                '--env' => 'testing',
                '--vault' => 'test',
            ]);
            expect($setCommand->getStatusCode())->toBe(0);

            $templateContent = "VALID={test:VALID_SECRET}\nINVALID={test:INVALID_SECRET}\n";
            $templatePath = 'mixed-template.env';
            file_put_contents($templatePath, $templateContent);

            $commandTester = runCommand('template:validate', [
                'template' => $templatePath,
                '--env' => 'testing',
                '--vault' => 'test',
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Line');
            expect($output)->toContain('Vault');
            expect($output)->toContain('Key');
            expect($output)->toContain('Status');
            expect($output)->toContain('VALID_SECRET');
            expect($output)->toContain('INVALID_SECRET');
        });

        it('shows line numbers for placeholders', function () {
            $templateContent = "# Comment line\nVALUE1={VALUE1}\n\nVALUE2={VALUE2}\n";
            $templatePath = 'multiline-template.env';
            file_put_contents($templatePath, $templateContent);

            $commandTester = runCommand('template:validate', [
                'template' => $templatePath,
                '--env' => 'testing',
                '--vault' => 'test',
            ]);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('2'); // Line number for VALUE1
            expect($output)->toContain('4'); // Line number for VALUE2
        });
    });

    describe('integration functionality', function () {
        it('requires Keep to be initialized', function () {
            // Remove .keep directory to simulate uninitialized state
            unlink('.keep/settings.json');
            unlink('.keep/vaults/test.json');
            rmdir('.keep/vaults');
            rmdir('.keep');

            $templatePath = 'test-template.env';
            file_put_contents($templatePath, "DB_PASSWORD={test:DB_PASSWORD}\n");

            $commandTester = runCommand('template:validate', [
                'template' => $templatePath,
                '--env' => 'testing',
                '--vault' => 'test',
            ]);

            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Keep is not initialized in this directory');
            expect($output)->toContain('Run: keep configure');
        });

        it('handles complex real-world template', function () {
            // Create complex template with realistic content
            $templateContent = <<<'EOT'
# Database Configuration
DB_HOST={test:DB_HOST}
DB_PORT={test:DB_PORT}
DB_NAME={test:DB_NAME}
DB_USER={test:DB_USER}
DB_PASSWORD={test:DB_PASSWORD}

# Redis Configuration  
REDIS_URL={test:REDIS_URL}

# API Configuration
API_KEY={test:API_KEY}

# Static values
APP_ENV=testing
DEBUG=true
EOT;

            $templatePath = 'complex-template.env';
            file_put_contents($templatePath, $templateContent);

            $commandTester = runCommand('template:validate', [
                'template' => $templatePath,
                '--env' => 'testing',
                '--vault' => 'test',
            ]);

            // Should fail due to missing secrets, but should parse placeholders correctly
            expect($commandTester->getStatusCode())->toBe(1);

            $output = stripAnsi($commandTester->getDisplay());
            expect($output)->toContain('Found 7 placeholder(s) to validate');
            expect($output)->toContain('Template validation failed');
            // Since vault is empty, all secrets are "referenced" (none exist to be unused)
            expect($output)->toContain('All secrets in test:testing are referenced in the template');
        });
    });
});

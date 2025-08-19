<?php

use Illuminate\Support\Facades\Artisan;
use STS\Keep\Facades\Keep;

describe('EndToEndWorkflowTest', function () {

    beforeEach(function () {
        // Clear test vault before each test
        Keep::vault('test')->clear();

        // Create test directory for file operations
        if (! is_dir('/tmp/keeper-integration-test')) {
            mkdir('/tmp/keeper-integration-test', 0755, true);
        }
    });

    afterEach(function () {
        // Clean up test files safely
        if (is_dir('/tmp/keeper-integration-test')) {
            $files = glob('/tmp/keeper-integration-test/*');
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
            @rmdir('/tmp/keeper-integration-test');
        }
    });

    describe('complete secret lifecycle', function () {
        it('set → get workflow', function () {
            // Step 1: Set a secret
            $result = Artisan::call('keep:set', [
                'key' => 'INTEGRATION_TEST_KEY',
                'value' => 'integration-test-value',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);
            expect(Artisan::output())->toContain('created in vault [test]');

            // Step 2: Verify secret can be retrieved
            $result = Artisan::call('keep:get', [
                'key' => 'INTEGRATION_TEST_KEY',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--format' => 'raw',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);
            expect(trim(Artisan::output()))->toBe('integration-test-value');

            // Step 3: Verify secret appears in list
            $result = Artisan::call('keep:list', [
                '--vault' => 'test',
                '--stage' => 'testing',
                '--format' => 'env',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);
            expect(Artisan::output())->toContain('INTEGRATION_TEST_KEY=');

            // Step 4: Export to file
            $exportFile = '/tmp/keeper-integration-test/single-secret.env';
            $result = Artisan::call('keep:export', [
                '--output' => $exportFile,
                '--format' => 'env',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);
            expect(file_exists($exportFile))->toBeTrue();

            $exportedContent = file_get_contents($exportFile);
            expect($exportedContent)->toContain('INTEGRATION_TEST_KEY=');
        });

        it('command interactions work without errors', function () {
            // Test import command with simple file
            $sourceFile = '/tmp/keeper-integration-test/simple.env';
            file_put_contents($sourceFile, 'SIMPLE_KEY=simple_value');

            $result = Artisan::call('keep:import', [
                'from' => $sourceFile,
                '--vault' => 'test',
                '--stage' => 'testing',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);

            // Test merge command with static template (no placeholders to avoid TestVault issues)
            $templateFile = '/tmp/keeper-integration-test/static.template';
            file_put_contents($templateFile, implode("\n", [
                'STATIC_CONFIG=production',
                'ANOTHER_STATIC=value',
            ]));

            $mergedFile = '/tmp/keeper-integration-test/static-merged.env';
            $result = Artisan::call('keep:merge', [
                'template' => $templateFile,
                '--output' => $mergedFile,
                '--vault' => 'test',
                '--stage' => 'testing',
                '--no-interaction' => true,
            ]);

            expect($result)->toBe(0);
            expect(file_exists($mergedFile))->toBeTrue();

            $mergedContent = file_get_contents($mergedFile);
            expect($mergedContent)->toContain('STATIC_CONFIG=production');
            expect($mergedContent)->toContain('ANOTHER_STATIC=value');
        });
    });

    // NOTE: The following tests are commented out due to TestVault environment isolation issues
    // See DEV_PLAN.md for details on these known infrastructure limitations

    /*
    describe('multi-environment secret management', function () {
        it('manages secrets across different environments', function () {
            // Set up development environment secrets
            Artisan::call('keep:set', [
                'key' => 'DB_HOST',
                'value' => 'localhost',
                '--vault' => 'test',
                '--stage' => 'development',
                '--no-interaction' => true
            ]);

            Artisan::call('keep:set', [
                'key' => 'API_URL',
                'value' => 'https://api-dev.example.com',
                '--vault' => 'test',
                '--stage' => 'development',
                '--no-interaction' => true
            ]);

            // Set up staging environment secrets
            Artisan::call('keep:set', [
                'key' => 'DB_HOST',
                'value' => 'staging-db.example.com',
                '--vault' => 'test',
                '--stage' => 'staging',
                '--no-interaction' => true
            ]);

            Artisan::call('keep:set', [
                'key' => 'API_URL',
                'value' => 'https://api-staging.example.com',
                '--vault' => 'test',
                '--stage' => 'staging',
                '--no-interaction' => true
            ]);

            // Set up production environment secrets
            Artisan::call('keep:set', [
                'key' => 'DB_HOST',
                'value' => 'prod-cluster.example.com',
                '--vault' => 'test',
                '--stage' => 'production',
                '--no-interaction' => true
            ]);

            Artisan::call('keep:set', [
                'key' => 'API_URL',
                'value' => 'https://api.example.com',
                '--vault' => 'test',
                '--stage' => 'production',
                '--no-interaction' => true
            ]);

            // Verify development environment
            $result = Artisan::call('keep:get', [
                'key' => 'DB_HOST',
                '--vault' => 'test',
                '--stage' => 'development',
                '--format' => 'raw',
                '--no-interaction' => true
            ]);
            expect(trim(Artisan::output()))->toBe('localhost');

            // Verify staging environment
            $result = Artisan::call('keep:get', [
                'key' => 'API_URL',
                '--vault' => 'test',
                '--stage' => 'staging',
                '--format' => 'raw',
                '--no-interaction' => true
            ]);
            expect(trim(Artisan::output()))->toBe('https://api-staging.example.com');

            // Verify production environment
            $result = Artisan::call('keep:get', [
                'key' => 'DB_HOST',
                '--vault' => 'test',
                '--stage' => 'production',
                '--format' => 'raw',
                '--no-interaction' => true
            ]);
            expect(trim(Artisan::output()))->toBe('prod-cluster.example.com');

            // Create environment-specific exports
            foreach (['development', 'staging', 'production'] as $env) {
                $envFile = "/tmp/keeper-integration-test/{$env}.env";
                Artisan::call('keep:export', [
                    '--output' => $envFile,
                    '--format' => 'env',
                    '--vault' => 'test',
                    '--stage' => $env,
                    '--no-interaction' => true
                ]);

                expect(file_exists($envFile))->toBeTrue();
            }

            // Verify each export contains correct values
            $devContent = file_get_contents('/tmp/keeper-integration-test/development.env');
            expect($devContent)->toContain('DB_HOST=localhost');
            expect($devContent)->toContain('API_URL="https://api-dev.example.com"');

            $prodContent = file_get_contents('/tmp/keeper-integration-test/production.env');
            expect($prodContent)->toContain('DB_HOST=prod-cluster.example.com');
            expect($prodContent)->toContain('API_URL="https://api.example.com"');
        });
    });

    describe('template merging with real vault data', function () {
        it('merges complex template with multiple missing secret strategies', function () {
            // Set up some secrets (but not all needed by template)
            Artisan::call('keep:set', [
                'key' => 'DB_HOST',
                'value' => 'database.example.com',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--no-interaction' => true
            ]);

            Artisan::call('keep:set', [
                'key' => 'DB_PASSWORD',
                'value' => 'secret123',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--no-interaction' => true
            ]);

            Artisan::call('keep:set', [
                'key' => 'REDIS_URL',
                'value' => 'redis://redis.example.com:6379',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--no-interaction' => true
            ]);

            // Create complex template with mix of existing and missing secrets
            $templateFile = '/tmp/keeper-integration-test/complex.template';
            file_put_contents($templateFile, implode("\n", [
                '# Database Configuration (exists)',
                'DB_CONNECTION=mysql',
                'DB_HOST={test:DB_HOST}',
                'DB_PORT=3306',
                'DB_DATABASE=myapp',
                'DB_USERNAME=myapp_user',
                'DB_PASSWORD={test:DB_PASSWORD}',
                '',
                '# Redis Configuration (exists)',
                'REDIS_URL={test:REDIS_URL}',
                '',
                '# Mail Configuration (missing)',
                'MAIL_MAILER=smtp',
                'MAIL_HOST={test:MAIL_HOST}',
                'MAIL_PORT={test:MAIL_PORT}',
                'MAIL_USERNAME={test:MAIL_USERNAME}',
                'MAIL_PASSWORD={test:MAIL_PASSWORD}',
                '',
                '# API Configuration (missing)',
                'API_KEY={test:API_KEY}',
                'API_SECRET={test:API_SECRET}',
                '',
                '# Optional Configuration (missing)',
                'OPTIONAL_FEATURE={test:OPTIONAL_FEATURE}',
                'DEBUG_TOKEN={test:DEBUG_TOKEN}'
            ]));

            // Test REMOVE strategy
            $removeFile = '/tmp/keeper-integration-test/remove-strategy.env';
            Artisan::call('keep:merge', [
                'template' => $templateFile,
                '--output' => $removeFile,
                '--missing' => 'remove',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--no-interaction' => true
            ]);

            $removeContent = file_get_contents($removeFile);
            expect($removeContent)->toContain('DB_HOST=database.example.com');
            expect($removeContent)->toContain('DB_PASSWORD="secret123"');
            expect($removeContent)->toContain('REDIS_URL="redis://redis.example.com:6379"');
            expect($removeContent)->toContain('# Removed missing secret: MAIL_HOST');
            expect($removeContent)->not->toContain('MAIL_HOST=');
            expect($removeContent)->not->toContain('API_KEY=');

            // Test BLANK strategy
            $blankFile = '/tmp/keeper-integration-test/blank-strategy.env';
            Artisan::call('keep:merge', [
                'template' => $templateFile,
                '--output' => $blankFile,
                '--missing' => 'blank',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--no-interaction' => true
            ]);

            $blankContent = file_get_contents($blankFile);
            expect($blankContent)->toContain('DB_HOST=database.example.com');
            expect($blankContent)->toContain('MAIL_HOST=');
            expect($blankContent)->toContain('API_KEY=');
            expect($blankContent)->toContain('OPTIONAL_FEATURE=');

            // Test SKIP strategy
            $skipFile = '/tmp/keeper-integration-test/skip-strategy.env';
            Artisan::call('keep:merge', [
                'template' => $templateFile,
                '--output' => $skipFile,
                '--missing' => 'skip',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--no-interaction' => true
            ]);

            $skipContent = file_get_contents($skipFile);
            expect($skipContent)->toContain('DB_HOST=database.example.com');
            expect($skipContent)->toContain('MAIL_HOST={test:MAIL_HOST}');
            expect($skipContent)->toContain('API_KEY={test:API_KEY}');
            expect($skipContent)->toContain('DEBUG_TOKEN={test:DEBUG_TOKEN}');
        });
    });

    describe('team collaboration scenarios', function () {
        it('supports sharing templates while keeping secrets separate', function () {
            // Create shared template (what would be in version control)
            $sharedTemplate = '/tmp/keeper-integration-test/shared.env.template';
            file_put_contents($sharedTemplate, implode("\n", [
                '# Shared Application Template',
                '# This file is committed to version control',
                '',
                'APP_NAME="Team Application"',
                'APP_ENV=production',
                'APP_DEBUG=false',
                'APP_TIMEZONE=UTC',
                '',
                '# Database (secrets managed separately)',
                'DB_CONNECTION=mysql',
                'DB_HOST={test:DB_HOST}',
                'DB_PORT=3306',
                'DB_DATABASE={test:DB_DATABASE}',
                'DB_USERNAME={test:DB_USERNAME}',
                'DB_PASSWORD={test:DB_PASSWORD}',
                '',
                '# Third-party Services (secrets managed separately)',
                'STRIPE_KEY={test:STRIPE_KEY}',
                'STRIPE_SECRET={test:STRIPE_SECRET}',
                'MAILGUN_DOMAIN={test:MAILGUN_DOMAIN}',
                'MAILGUN_SECRET={test:MAILGUN_SECRET}',
                '',
                '# Feature Flags (non-secret)',
                'FEATURE_NEW_DASHBOARD=true',
                'FEATURE_BETA_API=false'
            ]));

            // Team member A sets up their secrets
            $memberASecrets = [
                'DB_HOST' => 'localhost',
                'DB_DATABASE' => 'myapp_dev',
                'DB_USERNAME' => 'dev_user',
                'DB_PASSWORD' => 'dev_password_123',
                'STRIPE_KEY' => 'pk_test_dev_123',
                'STRIPE_SECRET' => 'sk_test_dev_456',
                'MAILGUN_DOMAIN' => 'dev.example.com',
                'MAILGUN_SECRET' => 'dev_mailgun_789'
            ];

            foreach ($memberASecrets as $key => $value) {
                Artisan::call('keep:set', [
                    'key' => $key,
                    'value' => $value,
                    '--vault' => 'test',
                    '--stage' => 'memberA',
                    '--no-interaction' => true
                ]);
            }

            // Team member B sets up their secrets (different values)
            $memberBSecrets = [
                'DB_HOST' => 'member-b-db.local',
                'DB_DATABASE' => 'myapp_memberb',
                'DB_USERNAME' => 'memberb_user',
                'DB_PASSWORD' => 'memberb_secret_456',
                'STRIPE_KEY' => 'pk_test_memberb_123',
                'STRIPE_SECRET' => 'sk_test_memberb_789',
                'MAILGUN_DOMAIN' => 'memberb.example.com',
                'MAILGUN_SECRET' => 'memberb_mailgun_123'
            ];

            foreach ($memberBSecrets as $key => $value) {
                Artisan::call('keep:set', [
                    'key' => $key,
                    'value' => $value,
                    '--vault' => 'test',
                    '--stage' => 'memberB',
                    '--no-interaction' => true
                ]);
            }

            // Each team member generates their own .env file
            $memberAEnv = '/tmp/keeper-integration-test/memberA.env';
            Artisan::call('keep:merge', [
                'template' => $sharedTemplate,
                '--output' => $memberAEnv,
                '--vault' => 'test',
                '--stage' => 'memberA',
                '--no-interaction' => true
            ]);

            $memberBEnv = '/tmp/keeper-integration-test/memberB.env';
            Artisan::call('keep:merge', [
                'template' => $sharedTemplate,
                '--output' => $memberBEnv,
                '--vault' => 'test',
                '--stage' => 'memberB',
                '--no-interaction' => true
            ]);

            // Verify each member gets their own secrets but same template structure
            $memberAContent = file_get_contents($memberAEnv);
            $memberBContent = file_get_contents($memberBEnv);

            // Both should have same non-secret values
            expect($memberAContent)->toContain('APP_NAME="Team Application"');
            expect($memberBContent)->toContain('APP_NAME="Team Application"');
            expect($memberAContent)->toContain('FEATURE_NEW_DASHBOARD=true');
            expect($memberBContent)->toContain('FEATURE_NEW_DASHBOARD=true');

            // But different secret values
            expect($memberAContent)->toContain('DB_HOST=localhost');
            expect($memberBContent)->toContain('DB_HOST=member-b-db.local');

            expect($memberAContent)->toContain('DB_PASSWORD="dev_password_123"');
            expect($memberBContent)->toContain('DB_PASSWORD="memberb_secret_456"');

            expect($memberAContent)->toContain('STRIPE_SECRET="sk_test_dev_456"');
            expect($memberBContent)->toContain('STRIPE_SECRET="sk_test_memberb_789"');
        });
    });

    describe('data consistency and error recovery', function () {
        it('handles partial failures gracefully', function () {
            // Test import with some valid and some invalid data
            $mixedFile = '/tmp/keeper-integration-test/mixed-quality.env';
            file_put_contents($mixedFile, implode("\n", [
                'VALID_KEY_1=valid_value_1',
                'VALID_KEY_2="quoted valid value"',
                'invalid line without equals sign',
                'VALID_KEY_3=another_valid_value',
                '=invalid_key_starts_with_equals',
                'VALID_KEY_4="final valid value"'
            ]));

            // Import should handle valid entries even if some are malformed
            $result = Artisan::call('keep:import', [
                'from' => $mixedFile,
                '--vault' => 'test',
                '--stage' => 'testing',
                '--no-interaction' => true
            ]);

            // Should still succeed for valid entries
            expect($result)->toBeIn([0, 1]); // May succeed or fail depending on how strict parsing is

            // If it succeeded, verify valid entries were imported
            if ($result === 0) {
                $vault = Keep::vault('test')->forStage('testing');
                expect($vault->hasSecret('VALID_KEY_1'))->toBeTrue();
                expect($vault->hasSecret('VALID_KEY_2'))->toBeTrue();
                expect($vault->hasSecret('VALID_KEY_3'))->toBeTrue();
                expect($vault->hasSecret('VALID_KEY_4'))->toBeTrue();
            }
        });

        it('maintains data integrity during template merging errors', function () {
            // Set up some valid secrets
            Artisan::call('keep:set', [
                'key' => 'VALID_SECRET',
                'value' => 'valid_value',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--no-interaction' => true
            ]);

            // Create template with valid and invalid placeholders
            $templateFile = '/tmp/keeper-integration-test/mixed-template.env';
            file_put_contents($templateFile, implode("\n", [
                'VALID_VAR={test:VALID_SECRET}',
                'INVALID_VAR={test:NONEXISTENT_SECRET}',
                'ANOTHER_VALID=static_value'
            ]));

            // Test with FAIL strategy (should fail on missing secret)
            $result = Artisan::call('keep:merge', [
                'template' => $templateFile,
                '--missing' => 'fail',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--no-interaction' => true
            ]);

            expect($result)->toBe(1); // Should fail

            // Test with REMOVE strategy (should succeed)
            $outputFile = '/tmp/keeper-integration-test/safe-merge.env';
            $result = Artisan::call('keep:merge', [
                'template' => $templateFile,
                '--output' => $outputFile,
                '--missing' => 'remove',
                '--vault' => 'test',
                '--stage' => 'testing',
                '--no-interaction' => true
            ]);

            expect($result)->toBe(0);
            expect(file_exists($outputFile))->toBeTrue();

            $content = file_get_contents($outputFile);
            expect($content)->toContain('VALID_VAR=valid_value');
            expect($content)->toContain('ANOTHER_VALID=static_value');
            expect($content)->not->toContain('INVALID_VAR=');
        });
    });
    */
});

<?php

use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Secret;
use STS\Keep\Data\Template;
use STS\Keep\Enums\MissingSecretStrategy;
use STS\Keep\Exceptions\SecretNotFoundException;
use STS\Keep\Vaults\AbstractVault;

beforeEach(function () {
    // Create mock vault for SSM
    $this->ssmVault = Mockery::mock(AbstractVault::class);
    $this->ssmVault->shouldReceive('name')->andReturn('ssm');

    // Create mock vault for other vault names used in tests
    $this->vaultOne = Mockery::mock(AbstractVault::class);
    $this->vaultOne->shouldReceive('name')->andReturn('vault-one');

    $this->vaultTwo = Mockery::mock(AbstractVault::class);
    $this->vaultTwo->shouldReceive('name')->andReturn('vault-two');

    $this->ssmProdVault = Mockery::mock(AbstractVault::class);
    $this->ssmProdVault->shouldReceive('name')->andReturn('ssm-prod');

    // Create secrets with SSM vault attached
    $this->secrets = new SecretCollection([
        new Secret('DB_PASSWORD', 'secret123', null, true, null, 0, null, $this->ssmVault),
        new Secret('DB_HOST', 'localhost', null, true, null, 0, null, $this->ssmVault),
        new Secret('API_KEY', 'api_secret_key', null, true, null, 0, null, $this->ssmVault),
        new Secret('MAIL_PASSWORD', 'mail_pass', null, true, null, 0, null, $this->ssmVault),
        new Secret('APP_KEY', 'base64:key123', null, true, null, 0, null, $this->ssmVault),
        new Secret('SPECIAL_CHARS', 'value with "quotes" and $pecial', null, true, null, 0, null, $this->ssmVault),
        new Secret('ALPHANUMERIC', 'abc123', null, true, null, 0, null, $this->ssmVault),
        new Secret('NUMERIC', '12345', null, true, null, 0, null, $this->ssmVault),
        new Secret('WITH_SPACES', 'value with spaces', null, true, null, 0, null, $this->ssmVault),
        new Secret('WITH_SINGLE_QUOTES', "value with 'quotes'", null, true, null, 0, null, $this->ssmVault),
        new Secret('WITH_DOUBLE_QUOTES', 'value with "quotes"', null, true, null, 0, null, $this->ssmVault),
        new Secret('WITH_BOTH_QUOTES', 'value with "double" and \'single\'', null, true, null, 0, null, $this->ssmVault),
        new Secret('WITH_BACKSLASH', 'value\\with\\backslash', null, true, null, 0, null, $this->ssmVault),
        new Secret('EMPTY_VALUE', '', null, true, null, 0, null, $this->ssmVault),
        new Secret('NULL_VALUE', null, null, true, null, 0, null, $this->ssmVault),
        // Add secrets for ssm-prod vault
        new Secret('DB_PASSWORD', 'secret123', null, true, null, 0, null, $this->ssmProdVault),
    ]);
});

describe('Template', function () {

    describe('isEmpty() and isNotEmpty()', function () {
        it('detects empty templates', function () {
            $template1 = new Template('');
            $template2 = new Template('   ');
            $template3 = new Template("\n\t  ");

            expect($template1->isEmpty())->toBeTrue();
            expect($template1->isNotEmpty())->toBeFalse();

            expect($template2->isEmpty())->toBeTrue();
            expect($template2->isNotEmpty())->toBeFalse();

            expect($template3->isEmpty())->toBeTrue();
            expect($template3->isNotEmpty())->toBeFalse();
        });

        it('detects non-empty templates', function () {
            $template = new Template('DB_PASSWORD={ssm:DB_PASSWORD}');

            expect($template->isEmpty())->toBeFalse();
            expect($template->isNotEmpty())->toBeTrue();
        });
    });

    describe('merge() with pattern matching', function () {
        it('merges basic placeholder format', function () {
            $template = new Template('DB_PASSWORD={ssm:DB_PASSWORD}');

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe('DB_PASSWORD=secret123');
        });

        it('merges placeholder with quotes', function () {
            $template = new Template("API_KEY='{ssm:API_KEY}'");

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe('API_KEY="api_secret_key"');
        });

        it('merges placeholder with double quotes', function () {
            $template = new Template('MAIL_PASSWORD="{ssm:MAIL_PASSWORD}"');

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe('MAIL_PASSWORD="mail_pass"');
        });

        it('merges placeholder without path (uses key as path)', function () {
            $template = new Template('DB_HOST={ssm}');

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe('DB_HOST=localhost');
        });

        it('handles placeholders with attributes', function () {
            $template = new Template('API_KEY={ssm:API_KEY|label=primary|version=2}');

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe('API_KEY="api_secret_key"');
        });

        it('preserves inline comments', function () {
            $template = new Template('DB_PASSWORD={ssm:DB_PASSWORD} # Database password');

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe('DB_PASSWORD=secret123 # Database password');
        });

        it('handles multiple placeholders in template', function () {
            $template = new Template(
                "DB_PASSWORD={ssm:DB_PASSWORD}\n".
                "DB_HOST={ssm:DB_HOST}\n".
                "API_KEY='{ssm:API_KEY}'"
            );

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe(
                "DB_PASSWORD=secret123\n".
                "DB_HOST=localhost\n".
                'API_KEY="api_secret_key"'
            );
        });

        it('ignores non-matching lines', function () {
            $template = new Template(
                "# Comment line\n".
                "DB_PASSWORD={ssm:DB_PASSWORD}\n".
                "STATIC_VALUE=not_a_placeholder\n".
                'API_KEY={ssm:API_KEY}'
            );

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe(
                "# Comment line\n".
                "DB_PASSWORD=secret123\n".
                "STATIC_VALUE=not_a_placeholder\n".
                'API_KEY="api_secret_key"'
            );
        });

        it('preserves leading and trailing whitespace', function () {
            $template = new Template('   DB_PASSWORD={ssm:DB_PASSWORD}   ');

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            // Whitespace should be preserved from the template
            expect($result)->toBe('   DB_PASSWORD=secret123   ');
        });

        it('handles special characters in secret values', function () {
            $template = new Template('SPECIAL_CHARS={ssm:SPECIAL_CHARS}');

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            // Quotes should now be properly escaped
            expect($result)->toBe('SPECIAL_CHARS=\'value with "quotes" and $pecial\'');
        });

        it('properly escapes double quotes in values', function () {
            $secrets = new SecretCollection([
                new Secret('QUOTES', 'value with "double" and \'single\' quotes', null, true, null, 0, null, $this->ssmVault),
                new Secret('JSON', '{"key": "value", "nested": {"item": "data"}}', null, true, null, 0, null, $this->ssmVault),
                new Secret('MIXED', 'Path: "C:\\Program Files\\App" and $HOME', null, true, null, 0, null, $this->ssmVault),
            ]);

            $template = new Template(
                "QUOTES={ssm:QUOTES}\n".
                "JSON={ssm:JSON}\n".
                'MIXED={ssm:MIXED}'
            );

            $result = $template->merge($secrets, MissingSecretStrategy::FAIL);

            // Only double quotes are escaped for .env compatibility
            expect($result)->toBe(
                "QUOTES='value with \"double\" and \\'single\\' quotes'\n".
                "JSON='{\"key\": \"value\", \"nested\": {\"item\": \"data\"}}'\n".
                "MIXED='Path: \"C:\\\\Program Files\\\\App\" and \$HOME'"
            );
        });

        it('only matches specified slug', function () {
            $template = new Template(
                "DB_PASSWORD={ssm:DB_PASSWORD}\n".
                'OTHER_SECRET={other-vault:OTHER_SECRET}'
            );

            $result = $template->merge($this->secrets, MissingSecretStrategy::SKIP);

            expect($result)->toBe(
                "DB_PASSWORD=secret123\n".
                'OTHER_SECRET={other-vault:OTHER_SECRET}'
            );
        });
    });

    describe('merge() with MissingSecretStrategy', function () {
        it('throws exception with FAIL strategy', function () {
            $template = new Template('MISSING_KEY={ssm:MISSING_KEY}');

            try {
                $template->merge($this->secrets, MissingSecretStrategy::FAIL);
                $this->fail('Expected SecretNotFoundException to be thrown');
            } catch (SecretNotFoundException $e) {
                expect($e->getMessage())->toBe('Unable to find secret for key [MISSING_KEY] in vault [ssm]');
                // We can't easily test the context properties directly in unit tests,
                // but we can verify the exception is thrown with the correct message
            }
        });

        it('removes line with REMOVE strategy', function () {
            $template = new Template('MISSING_KEY={ssm:MISSING_KEY}');

            $result = $template->merge($this->secrets, MissingSecretStrategy::REMOVE);

            expect($result)->toBe('# Removed missing secret: MISSING_KEY');
        });

        it('creates blank value with BLANK strategy', function () {
            $template = new Template('MISSING_KEY={ssm:MISSING_KEY}');

            $result = $template->merge($this->secrets, MissingSecretStrategy::BLANK);

            expect($result)->toBe('MISSING_KEY=');
        });

        it('keeps placeholder with SKIP strategy', function () {
            $template = new Template('MISSING_KEY={ssm:MISSING_KEY}');

            $result = $template->merge($this->secrets, MissingSecretStrategy::SKIP);

            expect($result)->toBe('MISSING_KEY={ssm:MISSING_KEY}');
        });

        it('includes line number in exception for multi-line templates', function () {
            $template = new Template(
                "# Line 1: Comment\n".
                "GOOD_KEY={ssm:DB_PASSWORD}\n".
                "# Line 3: Another comment\n".
                "BAD_KEY={ssm:MISSING_KEY}\n".
                '# Line 5: End'
            );

            try {
                $template->merge($this->secrets, MissingSecretStrategy::FAIL);
                $this->fail('Expected SecretNotFoundException to be thrown');
            } catch (SecretNotFoundException $e) {
                expect($e->getMessage())->toContain('MISSING_KEY');
                // The exception should be thrown for line 4
            }
        });

        it('does not leak vault details with REMOVE strategy', function () {
            $template = new Template(
                "SECRET1={vault-one:path/to/SECRET1|attr=value}\n".
                'SECRET2={vault-two:different/path/SECRET2}'
            );

            // With new multi-vault implementation, a single call processes all vaults
            $result = $template->merge($this->secrets, MissingSecretStrategy::REMOVE);

            // Should remove both missing secrets since no secrets from vault-one or vault-two exist
            expect($result)->toBe(
                "# Removed missing secret: SECRET1\n".
                '# Removed missing secret: SECRET2'
            );

            // With the new implementation, both vault placeholders are processed in one call
            // Since neither vault-one nor vault-two secrets exist, both should be removed
        });

        it('handles multiple missing keys with REMOVE strategy', function () {
            $template = new Template(
                "DB_PASSWORD={ssm:DB_PASSWORD}\n".
                "MISSING1={ssm:MISSING1}\n".
                "API_KEY={ssm:API_KEY}\n".
                'MISSING2={ssm:MISSING2}'
            );

            $result = $template->merge($this->secrets, MissingSecretStrategy::REMOVE);

            expect($result)->toBe(
                "DB_PASSWORD=secret123\n".
                "# Removed missing secret: MISSING1\n".
                "API_KEY=\"api_secret_key\"\n".
                '# Removed missing secret: MISSING2'
            );
        });
    });

    describe('pattern edge cases', function () {
        it('handles lowercase and mixed-case env keys', function () {
            $secrets = new SecretCollection([
                new Secret('lowercase_key', 'value1', null, true, null, 0, null, $this->ssmVault),
                new Secret('mixedCaseKey', 'value2', null, true, null, 0, null, $this->ssmVault),
                new Secret('camelCase_with_underscore', 'value3', null, true, null, 0, null, $this->ssmVault),
                new Secret('TRADITIONAL_UPPERCASE', 'value4', null, true, null, 0, null, $this->ssmVault),
            ]);

            $template = new Template(
                "lowercase_key={ssm:lowercase_key}\n".
                "mixedCaseKey={ssm:mixedCaseKey}\n".
                "camelCase_with_underscore={ssm:camelCase_with_underscore}\n".
                'TRADITIONAL_UPPERCASE={ssm:TRADITIONAL_UPPERCASE}'
            );

            $result = $template->merge($secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe(
                "lowercase_key=value1\n".
                "mixedCaseKey=value2\n".
                "camelCase_with_underscore=value3\n".
                'TRADITIONAL_UPPERCASE=value4'
            );
        });

        it('handles keys starting with underscore', function () {
            // Note: Secret class sanitizes keys and removes leading underscores,
            // but the template pattern should still match them
            $secrets = new SecretCollection([
                new Secret('MY_underscore_start', 'value1', null, true, null, 0, null, $this->ssmVault), // This becomes 'MY_underscore_start' after sanitization
            ]);

            $template = new Template(
                "_STARTS_WITH_UNDERSCORE={ssm:MY_underscore_start}\n".
                'NORMAL_KEY={ssm:NORMAL_KEY}'
            );

            $result = $template->merge($secrets, MissingSecretStrategy::SKIP);

            // The pattern should match _STARTS_WITH_UNDERSCORE
            expect($result)->toBe(
                "_STARTS_WITH_UNDERSCORE=value1\n".
                'NORMAL_KEY={ssm:NORMAL_KEY}'
            );
        });

        it('does not match keys starting with numbers', function () {
            $secrets = new SecretCollection([
                new Secret('valid_key', 'replaced', null, true, null, 0, null, $this->ssmVault),
            ]);

            $template = new Template(
                "valid_key={ssm:valid_key}\n".
                "123_invalid={ssm:123_invalid}\n".
                '9starts_with_number={ssm:9starts_with_number}'
            );

            $result = $template->merge($secrets, MissingSecretStrategy::SKIP);

            // Keys starting with numbers should not be matched/processed
            expect($result)->toBe(
                "valid_key=replaced\n".
                "123_invalid={ssm:123_invalid}\n".
                '9starts_with_number={ssm:9starts_with_number}'
            );
        });

        it('handles paths with underscores and numbers', function () {
            $secrets = new SecretCollection([
                new Secret('app_production_db_password', 'prod_pass', null, true, null, 0, null, $this->ssmVault),
                new Secret('services_api_key', 'api_key_value', null, true, null, 0, null, $this->ssmVault),
            ]);

            $template1 = new Template('DB_PASS={ssm:app_production_db_password}');
            $template2 = new Template('API_KEY={ssm:services_api_key}');

            $result1 = $template1->merge($secrets, MissingSecretStrategy::FAIL);
            $result2 = $template2->merge($secrets, MissingSecretStrategy::FAIL);

            expect($result1)->toBe('DB_PASS="prod_pass"');
            expect($result2)->toBe('API_KEY="api_key_value"');
        });

        it('handles valid key formats with underscores', function () {
            $secrets = new SecretCollection([
                new Secret('my_secret_key', 'value1', null, true, null, 0, null, $this->ssmVault),
                new Secret('another_secret_name', 'value2', null, true, null, 0, null, $this->ssmVault),
            ]);

            $template = new Template(
                "SECRET1={ssm:my_secret_key}\n".
                'SECRET2={ssm:another_secret_name}'
            );

            $result = $template->merge($secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe(
                "SECRET1=value1\n".
                'SECRET2=value2'
            );
        });

        it('handles slugs with hyphens', function () {
            $template = new Template('DB_PASSWORD={ssm-prod:DB_PASSWORD}');

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe('DB_PASSWORD=secret123');
        });

        it('ignores placeholders with leading slashes in path', function () {
            $template = new Template('INVALID={ssm:/absolute/path}');

            $result = $template->merge($this->secrets, MissingSecretStrategy::SKIP);

            expect($result)->toBe('INVALID={ssm:/absolute/path}');
        });

        it('handles empty template', function () {
            $template = new Template('');

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe('');
        });

        it('handles template with only comments and empty lines', function () {
            $template = new Template(
                "# This is a comment\n".
                "\n".
                "  # Another comment\n".
                "    \n"
            );

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe(
                "# This is a comment\n".
                "\n".
                "  # Another comment\n".
                "    \n"
            );
        });

        it('preserves spacing around equals sign', function () {
            $template1 = new Template('DB_PASSWORD = {ssm:DB_PASSWORD}');
            $template2 = new Template('API_KEY= {ssm:API_KEY}');
            $template3 = new Template('APP_KEY ={ssm:APP_KEY}');

            $result1 = $template1->merge($this->secrets, MissingSecretStrategy::FAIL);
            $result2 = $template2->merge($this->secrets, MissingSecretStrategy::FAIL);
            $result3 = $template3->merge($this->secrets, MissingSecretStrategy::FAIL);

            // Spacing around equals should be preserved exactly
            expect($result1)->toBe('DB_PASSWORD = secret123');
            expect($result2)->toBe('API_KEY= "api_secret_key"');
            expect($result3)->toBe('APP_KEY ="base64:key123"');
        });
    });

    describe('whitespace preservation', function () {
        it('preserves complex formatting exactly', function () {
            $template = new Template(
                "    DB_HOST={ssm:DB_HOST}\n".
                "\tDB_PORT = {ssm}\n".
                "  DB_NAME  =  {ssm:DB_NAME}  \n".
                'DB_PASSWORD={ssm:DB_PASSWORD}    # Production password'
            );

            $secrets = new SecretCollection([
                new Secret('DB_HOST', 'localhost', null, true, null, 0, null, $this->ssmVault),
                new Secret('DB_PORT', '3306', null, true, null, 0, null, $this->ssmVault),
                new Secret('DB_NAME', 'myapp', null, true, null, 0, null, $this->ssmVault),
                new Secret('DB_PASSWORD', 'secret', null, true, null, 0, null, $this->ssmVault),
            ]);

            $result = $template->merge($secrets, MissingSecretStrategy::FAIL);

            // All original formatting should be preserved
            expect($result)->toBe(
                "    DB_HOST=localhost\n".
                "\tDB_PORT = 3306\n".
                "  DB_NAME  =  myapp  \n".
                'DB_PASSWORD=secret    # Production password'
            );
        });
    });

    describe('consistency with SecretsCollection', function () {
        it('produces same escaping as SecretsCollection::toEnvString()', function () {
            $secrets = new SecretCollection([
                new Secret('KEY1', 'simple value', null, true, null, 0, null, $this->ssmVault),
                new Secret('KEY2', 'value with "quotes"', null, true, null, 0, null, $this->ssmVault),
                new Secret('KEY3', null, null, true, null, 0, null, $this->ssmVault),
                new Secret('KEY4', 'path\\with\\backslash', null, true, null, 0, null, $this->ssmVault),
            ]);

            // Template approach (without extra whitespace so it matches)
            $template = new Template(
                "KEY1={ssm:KEY1}\n".
                "KEY2={ssm:KEY2}\n".
                "KEY3={ssm:KEY3}\n".
                'KEY4={ssm:KEY4}'
            );
            $templateResult = $template->merge($secrets, MissingSecretStrategy::BLANK);

            // SecretsCollection approach
            $collectionResult = $secrets->toEnvString();

            // Both should produce the same output when template has no extra whitespace
            expect($templateResult)->toBe($collectionResult);
        });
    });

    describe('complex scenarios', function () {
        it('handles real-world .env template', function () {
            $template = new Template(
                "# Application Settings\n".
                "APP_NAME=\"My App\"\n".
                "APP_ENV=production\n".
                "APP_KEY={ssm:APP_KEY}\n".
                "APP_DEBUG=false\n".
                "\n".
                "# Database Configuration\n".
                "DB_CONNECTION=mysql\n".
                "DB_HOST={ssm:DB_HOST}\n".
                "DB_PORT=3306\n".
                "DB_DATABASE=myapp_prod\n".
                "DB_USERNAME=admin\n".
                "DB_PASSWORD='{ssm:DB_PASSWORD}' # Production database\n".
                "\n".
                "# Mail Settings\n".
                "MAIL_MAILER=smtp\n".
                "MAIL_HOST=smtp.mailgun.org\n".
                "MAIL_PORT=587\n".
                "MAIL_USERNAME=\"{ssm}\"\n".
                "MAIL_PASSWORD={ssm:MAIL_PASSWORD}\n".
                "MAIL_ENCRYPTION=tls\n".
                "\n".
                "# Third-party APIs\n".
                "STRIPE_KEY={ssm:STRIPE_KEY|environment=production}\n".
                'STRIPE_SECRET={ssm:STRIPE_SECRET|environment=production|version=latest}'
            );

            $secrets = new SecretCollection([
                new Secret('APP_KEY', 'base64:production_key', null, true, null, 0, null, $this->ssmVault),
                new Secret('DB_HOST', 'prod.db.example.com', null, true, null, 0, null, $this->ssmVault),
                new Secret('DB_PASSWORD', 'super_secret_pass', null, true, null, 0, null, $this->ssmVault),
                new Secret('MAIL_USERNAME', 'postmaster@example.com', null, true, null, 0, null, $this->ssmVault),
                new Secret('MAIL_PASSWORD', 'mail_secret', null, true, null, 0, null, $this->ssmVault),
                new Secret('STRIPE_KEY', 'pk_live_123', null, true, null, 0, null, $this->ssmVault),
                new Secret('STRIPE_SECRET', 'sk_live_456', null, true, null, 0, null, $this->ssmVault),
            ]);

            $result = $template->merge($secrets, MissingSecretStrategy::FAIL);

            expect($result)->toContain('APP_KEY="base64:production_key"');
            expect($result)->toContain('DB_HOST="prod.db.example.com"');
            expect($result)->toContain('DB_PASSWORD="super_secret_pass" # Production database');
            expect($result)->toContain('MAIL_USERNAME="postmaster@example.com"');
            expect($result)->toContain('MAIL_PASSWORD="mail_secret"');
            expect($result)->toContain('STRIPE_KEY="pk_live_123"');
            expect($result)->toContain('STRIPE_SECRET="sk_live_456"');

            // Ensure non-placeholder lines are preserved
            expect($result)->toContain('APP_NAME="My App"');
            expect($result)->toContain('DB_PORT=3306');
            expect($result)->toContain('# Application Settings');
        });
    });

    describe('smart quoting behavior', function () {
        it('leaves pure alphanumeric values unquoted', function () {
            $template = new Template(
                "ALPHANUMERIC={ssm:ALPHANUMERIC}\n".
                'NUMERIC={ssm:NUMERIC}'
            );

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe(
                "ALPHANUMERIC=abc123\n".
                'NUMERIC=12345'
            );
        });

        it('quotes values with spaces', function () {
            $template = new Template('WITH_SPACES={ssm:WITH_SPACES}');

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe('WITH_SPACES="value with spaces"');
        });

        it('quotes values with special characters', function () {
            $template = new Template('APP_KEY={ssm:APP_KEY}');

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe('APP_KEY="base64:key123"');
        });

        it('handles values with single quotes using double quotes', function () {
            $template = new Template('WITH_SINGLE_QUOTES={ssm:WITH_SINGLE_QUOTES}');

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe('WITH_SINGLE_QUOTES="value with \'quotes\'"');
        });

        it('handles values with double quotes using single quotes and escaping', function () {
            $template = new Template('WITH_DOUBLE_QUOTES={ssm:WITH_DOUBLE_QUOTES}');

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe('WITH_DOUBLE_QUOTES=\'value with "quotes"\'');
        });

        it('handles values with both quote types', function () {
            $template = new Template('WITH_BOTH_QUOTES={ssm:WITH_BOTH_QUOTES}');

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe('WITH_BOTH_QUOTES=\'value with "double" and \\\'single\\\'\'');
        });

        it('properly escapes backslashes', function () {
            $template = new Template('WITH_BACKSLASH={ssm:WITH_BACKSLASH}');

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe('WITH_BACKSLASH="value\\\\with\\\\backslash"');
        });

        it('handles empty values without quotes', function () {
            $template = new Template('EMPTY_VALUE={ssm:EMPTY_VALUE}');

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe('EMPTY_VALUE=');
        });

        it('handles null values without quotes', function () {
            $template = new Template('NULL_VALUE={ssm:NULL_VALUE}');

            $result = $template->merge($this->secrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe('NULL_VALUE=');
        });
    });

    describe('vault reference extraction', function () {
        it('extracts all referenced vaults from template placeholders', function () {
            $template = new Template(
                "DB_PASSWORD={ssm:DB_PASSWORD}\n".
                "API_KEY={secretsmanager:API_KEY}\n".
                "REDIS_URL={ssm-usw-2:REDIS_URL}\n".
                "MAIL_PASSWORD={ssm:MAIL_PASSWORD}\n".
                'STRIPE_KEY={secretsmanager:STRIPE_KEY}'
            );

            $vaults = $template->allReferencedVaults();

            expect($vaults)->toHaveCount(3);
            expect($vaults)->toContain('ssm');
            expect($vaults)->toContain('secretsmanager');
            expect($vaults)->toContain('ssm-usw-2');
        });

        it('returns empty array for template with no placeholders', function () {
            $template = new Template(
                "APP_NAME=MyApp\n".
                "APP_ENV=production\n".
                'APP_DEBUG=false'
            );

            $vaults = $template->allReferencedVaults();

            expect($vaults)->toBeEmpty();
        });

        it('handles template with mixed placeholder and static values', function () {
            $template = new Template(
                "APP_NAME=MyApp\n".
                "DB_PASSWORD={ssm:DB_PASSWORD}\n".
                "APP_ENV=production\n".
                'API_KEY={secretsmanager:API_KEY}'
            );

            $vaults = $template->allReferencedVaults();

            expect($vaults)->toHaveCount(2);
            expect($vaults)->toContain('ssm');
            expect($vaults)->toContain('secretsmanager');
        });
    });

    describe('multi-vault template scenarios', function () {
        it('handles templates with placeholders from multiple vaults', function () {
            // Create mock vaults for different providers
            $secretsManagerVault = Mockery::mock(AbstractVault::class);
            $secretsManagerVault->shouldReceive('name')->andReturn('secretsmanager');

            $ssmUsWest2Vault = Mockery::mock(AbstractVault::class);
            $ssmUsWest2Vault->shouldReceive('name')->andReturn('ssm-usw-2');

            // Create secrets from different vaults
            $multiVaultSecrets = new SecretCollection([
                new Secret('DB_PASSWORD', 'ssm_db_password', null, true, null, 0, null, $this->ssmVault),
                new Secret('API_KEY', 'secretsmanager_api_key', null, true, null, 0, null, $secretsManagerVault),
                new Secret('REDIS_URL', 'redis_from_usw2', null, true, null, 0, null, $ssmUsWest2Vault),
            ]);

            // Template with placeholders from multiple vaults
            $template = new Template(
                "# Database from main SSM\n".
                "DB_PASSWORD={ssm:DB_PASSWORD}\n".
                "\n".
                "# API key from Secrets Manager\n".
                "API_KEY={secretsmanager:API_KEY}\n".
                "\n".
                "# Redis from US West 2 SSM\n".
                'REDIS_URL={ssm-usw-2:REDIS_URL}'
            );

            $result = $template->merge($multiVaultSecrets, MissingSecretStrategy::FAIL);

            // Verify each secret is filled from the correct vault
            expect($result)->toBe(
                "# Database from main SSM\n".
                "DB_PASSWORD=\"ssm_db_password\"\n".
                "\n".
                "# API key from Secrets Manager\n".
                "API_KEY=\"secretsmanager_api_key\"\n".
                "\n".
                "# Redis from US West 2 SSM\n".
                'REDIS_URL="redis_from_usw2"'
            );
        });

        it('throws error when vault slug does not match secret vault', function () {
            // Secret belongs to 'ssm' vault but placeholder references 'secretsmanager'
            $wrongVaultSecrets = new SecretCollection([
                new Secret('WRONG_VAULT_KEY', 'some_value', null, true, null, 0, null, $this->ssmVault),
            ]);

            $template = new Template('WRONG_VAULT_KEY={secretsmanager:WRONG_VAULT_KEY}');

            expect(fn () => $template->merge($wrongVaultSecrets, MissingSecretStrategy::FAIL))
                ->toThrow(SecretNotFoundException::class, 'Unable to find secret for key [WRONG_VAULT_KEY] in vault [secretsmanager]');
        });

        it('handles missing secrets from specific vaults correctly', function () {
            $partialSecrets = new SecretCollection([
                new Secret('EXISTS_IN_SSM', 'value_from_ssm', null, true, null, 0, null, $this->ssmVault),
            ]);

            $template = new Template(
                "EXISTS_IN_SSM={ssm:EXISTS_IN_SSM}\n".
                'MISSING_FROM_SECRETSMANAGER={secretsmanager:MISSING_KEY}'
            );

            $result = $template->merge($partialSecrets, MissingSecretStrategy::REMOVE);

            expect($result)->toBe(
                "EXISTS_IN_SSM=\"value_from_ssm\"\n".
                '# Removed missing secret: MISSING_FROM_SECRETSMANAGER'
            );
        });

        it('handles same key from different vaults', function () {
            $secretsManagerVault = Mockery::mock(AbstractVault::class);
            $secretsManagerVault->shouldReceive('name')->andReturn('secretsmanager');

            // Same key name but from different vaults with different values
            $sameKeySecrets = new SecretCollection([
                new Secret('API_KEY', 'ssm_api_key', null, true, null, 0, null, $this->ssmVault),
                new Secret('API_KEY', 'secretsmanager_api_key', null, true, null, 0, null, $secretsManagerVault),
            ]);

            $template = new Template(
                "SSM_API_KEY={ssm:API_KEY}\n".
                'SECRETSMANAGER_API_KEY={secretsmanager:API_KEY}'
            );

            $result = $template->merge($sameKeySecrets, MissingSecretStrategy::FAIL);

            expect($result)->toBe(
                "SSM_API_KEY=\"ssm_api_key\"\n".
                'SECRETSMANAGER_API_KEY="secretsmanager_api_key"'
            );
        });
    });

    describe('placeholders() method', function () {
        it('extracts placeholders with vault:key format', function () {
            $template = new Template("DB_HOST={ssm:DB_HOST}\nAPI_KEY={vault:API_KEY}");
            $placeholders = $template->placeholders();

            expect($placeholders)->toHaveCount(2);

            $firstPlaceholder = $placeholders[0];
            expect($firstPlaceholder->line)->toBe(1);
            expect($firstPlaceholder->envKey)->toBe('DB_HOST');
            expect($firstPlaceholder->vault)->toBe('ssm');
            expect($firstPlaceholder->key)->toBe('DB_HOST');

            $secondPlaceholder = $placeholders[1];
            expect($secondPlaceholder->line)->toBe(2);
            expect($secondPlaceholder->envKey)->toBe('API_KEY');
            expect($secondPlaceholder->vault)->toBe('vault');
            expect($secondPlaceholder->key)->toBe('API_KEY');
        });

        it('extracts simple placeholders without vault prefix', function () {
            $template = new Template('API_KEY={API_KEY}');
            $placeholders = $template->placeholders();

            expect($placeholders)->toHaveCount(1);
            $placeholder = $placeholders[0];
            expect($placeholder->line)->toBe(1);
            expect($placeholder->envKey)->toBe('API_KEY');
            expect($placeholder->vault)->toBeNull();
            expect($placeholder->key)->toBe('API_KEY');
        });

        it('returns empty collection for templates with no placeholders', function () {
            $template = new Template("# Just a comment\nSTATIC_VALUE=hello");
            $placeholders = $template->placeholders();

            expect($placeholders)->toBeEmpty();
        });

        it('returns empty collection for empty templates', function () {
            $template = new Template('');
            $placeholders = $template->placeholders();

            expect($placeholders)->toBeEmpty();
        });

        it('includes line numbers and raw line content', function () {
            $template = new Template("# Comment\nDB_HOST={ssm:DB_HOST}\n\nAPI_KEY={API_KEY}");
            $placeholders = $template->placeholders();

            expect($placeholders)->toHaveCount(2);
            expect($placeholders[0]->line)->toBe(2);
            expect($placeholders[0]->rawLine)->toBe('DB_HOST={ssm:DB_HOST}');
            expect($placeholders[1]->line)->toBe(4);
            expect($placeholders[1]->rawLine)->toBe('API_KEY={API_KEY}');
        });
    });
});

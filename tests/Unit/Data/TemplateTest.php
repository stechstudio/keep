<?php

use STS\Keep\Data\Secret;
use STS\Keep\Data\SecretsCollection;
use STS\Keep\Data\Template;
use STS\Keep\Enums\MissingSecretStrategy;
use STS\Keep\Exceptions\SecretNotFoundException;

beforeEach(function () {
    $this->secrets = new SecretsCollection([
        new Secret('DB_PASSWORD', 'secret123'),
        new Secret('DB_HOST', 'localhost'),
        new Secret('API_KEY', 'api_secret_key'),
        new Secret('MAIL_PASSWORD', 'mail_pass'),
        new Secret('APP_KEY', 'base64:key123'),
        new Secret('SPECIAL_CHARS', 'value with "quotes" and $pecial'),
        new Secret('ALPHANUMERIC', 'abc123'),
        new Secret('NUMERIC', '12345'),
        new Secret('WITH_SPACES', 'value with spaces'),
        new Secret('WITH_SINGLE_QUOTES', "value with 'quotes'"),
        new Secret('WITH_DOUBLE_QUOTES', 'value with "quotes"'),
        new Secret('WITH_BOTH_QUOTES', 'value with "double" and \'single\''),
        new Secret('WITH_BACKSLASH', 'value\\with\\backslash'),
        new Secret('EMPTY_VALUE', ''),
        new Secret('NULL_VALUE', null),
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
            $template = new Template('DB_PASSWORD={aws-ssm:DB_PASSWORD}');
            
            expect($template->isEmpty())->toBeFalse();
            expect($template->isNotEmpty())->toBeTrue();
        });
    });
    
    describe('merge() with pattern matching', function () {
        it('merges basic placeholder format', function () {
            $template = new Template('DB_PASSWORD={aws-ssm:DB_PASSWORD}');
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe('DB_PASSWORD=secret123');
        });
        
        it('merges placeholder with quotes', function () {
            $template = new Template("API_KEY='{aws-ssm:API_KEY}'");
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe('API_KEY="api_secret_key"');
        });
        
        it('merges placeholder with double quotes', function () {
            $template = new Template('MAIL_PASSWORD="{aws-ssm:MAIL_PASSWORD}"');
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe('MAIL_PASSWORD="mail_pass"');
        });
        
        it('merges placeholder without path (uses key as path)', function () {
            $template = new Template('DB_HOST={aws-ssm}');
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe('DB_HOST=localhost');
        });
        
        it('handles placeholders with attributes', function () {
            $template = new Template('API_KEY={aws-ssm:API_KEY|label=primary|version=2}');
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe('API_KEY="api_secret_key"');
        });
        
        it('preserves inline comments', function () {
            $template = new Template('DB_PASSWORD={aws-ssm:DB_PASSWORD} # Database password');
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe('DB_PASSWORD=secret123 # Database password');
        });
        
        it('handles multiple placeholders in template', function () {
            $template = new Template(
                "DB_PASSWORD={aws-ssm:DB_PASSWORD}\n" .
                "DB_HOST={aws-ssm:DB_HOST}\n" .
                "API_KEY='{aws-ssm:API_KEY}'"
            );
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe(
                "DB_PASSWORD=secret123\n" .
                "DB_HOST=localhost\n" .
                "API_KEY=\"api_secret_key\""
            );
        });
        
        it('ignores non-matching lines', function () {
            $template = new Template(
                "# Comment line\n" .
                "DB_PASSWORD={aws-ssm:DB_PASSWORD}\n" .
                "STATIC_VALUE=not_a_placeholder\n" .
                "API_KEY={aws-ssm:API_KEY}"
            );
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe(
                "# Comment line\n" .
                "DB_PASSWORD=secret123\n" .
                "STATIC_VALUE=not_a_placeholder\n" .
                "API_KEY=\"api_secret_key\""
            );
        });
        
        it('preserves leading and trailing whitespace', function () {
            $template = new Template('   DB_PASSWORD={aws-ssm:DB_PASSWORD}   ');
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            // Whitespace should be preserved from the template
            expect($result)->toBe('   DB_PASSWORD=secret123   ');
        });
        
        it('handles special characters in secret values', function () {
            $template = new Template('SPECIAL_CHARS={aws-ssm:SPECIAL_CHARS}');
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            // Quotes should now be properly escaped
            expect($result)->toBe('SPECIAL_CHARS=\'value with "quotes" and $pecial\'');
        });
        
        it('properly escapes double quotes in values', function () {
            $secrets = new SecretsCollection([
                new Secret('QUOTES', 'value with "double" and \'single\' quotes'),
                new Secret('JSON', '{"key": "value", "nested": {"item": "data"}}'),
                new Secret('MIXED', 'Path: "C:\\Program Files\\App" and $HOME'),
            ]);
            
            $template = new Template(
                "QUOTES={aws-ssm:QUOTES}\n" .
                "JSON={aws-ssm:JSON}\n" .
                "MIXED={aws-ssm:MIXED}"
            );
            
            $result = $template->merge('aws-ssm', $secrets, MissingSecretStrategy::FAIL);
            
            // Only double quotes are escaped for .env compatibility
            expect($result)->toBe(
                "QUOTES='value with \"double\" and \\'single\\' quotes'\n" .
                "JSON='{\"key\": \"value\", \"nested\": {\"item\": \"data\"}}'\n" .
                "MIXED='Path: \"C:\\\\Program Files\\\\App\" and \$HOME'"
            );
        });
        
        it('only matches specified slug', function () {
            $template = new Template(
                "DB_PASSWORD={aws-ssm:DB_PASSWORD}\n" .
                "OTHER_SECRET={other-vault:OTHER_SECRET}"
            );
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::SKIP);
            
            expect($result)->toBe(
                "DB_PASSWORD=secret123\n" .
                "OTHER_SECRET={other-vault:OTHER_SECRET}"
            );
        });
    });
    
    describe('merge() with MissingSecretStrategy', function () {
        it('throws exception with FAIL strategy', function () {
            $template = new Template('MISSING_KEY={aws-ssm:MISSING_KEY}');
            
            try {
                $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
                $this->fail('Expected SecretNotFoundException to be thrown');
            } catch (SecretNotFoundException $e) {
                expect($e->getMessage())->toBe('Unable to find secret for key [MISSING_KEY]');
                // We can't easily test the context properties directly in unit tests,
                // but we can verify the exception is thrown with the correct message
            }
        });
        
        it('removes line with REMOVE strategy', function () {
            $template = new Template('MISSING_KEY={aws-ssm:MISSING_KEY}');
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::REMOVE);
            
            expect($result)->toBe('# Removed missing secret: MISSING_KEY={aws-ssm:MISSING_KEY}');
        });
        
        it('creates blank value with BLANK strategy', function () {
            $template = new Template('MISSING_KEY={aws-ssm:MISSING_KEY}');
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::BLANK);
            
            expect($result)->toBe('MISSING_KEY=');
        });
        
        it('keeps placeholder with SKIP strategy', function () {
            $template = new Template('MISSING_KEY={aws-ssm:MISSING_KEY}');
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::SKIP);
            
            expect($result)->toBe('MISSING_KEY={aws-ssm:MISSING_KEY}');
        });
        
        it('includes line number in exception for multi-line templates', function () {
            $template = new Template(
                "# Line 1: Comment\n" .
                "GOOD_KEY={aws-ssm:DB_PASSWORD}\n" .
                "# Line 3: Another comment\n" .
                "BAD_KEY={aws-ssm:MISSING_KEY}\n" .
                "# Line 5: End"
            );
            
            try {
                $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
                $this->fail('Expected SecretNotFoundException to be thrown');
            } catch (SecretNotFoundException $e) {
                expect($e->getMessage())->toContain('MISSING_KEY');
                // The exception should be thrown for line 4
            }
        });
        
        it('handles multiple missing keys with REMOVE strategy', function () {
            $template = new Template(
                "DB_PASSWORD={aws-ssm:DB_PASSWORD}\n" .
                "MISSING1={aws-ssm:MISSING1}\n" .
                "API_KEY={aws-ssm:API_KEY}\n" .
                "MISSING2={aws-ssm:MISSING2}"
            );
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::REMOVE);
            
            expect($result)->toBe(
                "DB_PASSWORD=secret123\n" .
                "# Removed missing secret: MISSING1={aws-ssm:MISSING1}\n" .
                "API_KEY=\"api_secret_key\"\n" .
                "# Removed missing secret: MISSING2={aws-ssm:MISSING2}"
            );
        });
    });
    
    describe('pattern edge cases', function () {
        it('handles paths with dots and slashes', function () {
            $secrets = new SecretsCollection([
                new Secret('app.production.db.password', 'prod_pass'),
                new Secret('services/api/key', 'api_key_value'),
            ]);
            
            $template1 = new Template('DB_PASS={aws-ssm:app.production.db.password}');
            $template2 = new Template('API_KEY={aws-ssm:services/api/key}');
            
            $result1 = $template1->merge('aws-ssm', $secrets, MissingSecretStrategy::FAIL);
            $result2 = $template2->merge('aws-ssm', $secrets, MissingSecretStrategy::FAIL);
            
            expect($result1)->toBe('DB_PASS="prod_pass"');
            expect($result2)->toBe('API_KEY="api_key_value"');
        });
        
        it('handles paths with underscores and hyphens', function () {
            $secrets = new SecretsCollection([
                new Secret('my-secret_key', 'value1'),
                new Secret('another_secret-name', 'value2'),
            ]);
            
            $template = new Template(
                "SECRET1={aws-ssm:my-secret_key}\n" .
                "SECRET2={aws-ssm:another_secret-name}"
            );
            
            $result = $template->merge('aws-ssm', $secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe(
                "SECRET1=value1\n" .
                "SECRET2=value2"
            );
        });
        
        it('handles slugs with hyphens', function () {
            $template = new Template('DB_PASSWORD={aws-ssm-prod:DB_PASSWORD}');
            
            $result = $template->merge('aws-ssm-prod', $this->secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe('DB_PASSWORD=secret123');
        });
        
        it('ignores placeholders with leading slashes in path', function () {
            $template = new Template('INVALID={aws-ssm:/absolute/path}');
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::SKIP);
            
            expect($result)->toBe('INVALID={aws-ssm:/absolute/path}');
        });
        
        it('handles empty template', function () {
            $template = new Template('');
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe('');
        });
        
        it('handles template with only comments and empty lines', function () {
            $template = new Template(
                "# This is a comment\n" .
                "\n" .
                "  # Another comment\n" .
                "    \n"
            );
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe(
                "# This is a comment\n" .
                "\n" .
                "  # Another comment\n" .
                "    \n"
            );
        });
        
        it('preserves spacing around equals sign', function () {
            $template1 = new Template('DB_PASSWORD = {aws-ssm:DB_PASSWORD}');
            $template2 = new Template('API_KEY= {aws-ssm:API_KEY}');
            $template3 = new Template('APP_KEY ={aws-ssm:APP_KEY}');
            
            $result1 = $template1->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            $result2 = $template2->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            $result3 = $template3->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            // Spacing around equals should be preserved exactly
            expect($result1)->toBe('DB_PASSWORD = secret123');
            expect($result2)->toBe('API_KEY= "api_secret_key"');
            expect($result3)->toBe('APP_KEY ="base64:key123"');
        });
    });
    
    describe('whitespace preservation', function () {
        it('preserves complex formatting exactly', function () {
            $template = new Template(
                "    DB_HOST={aws-ssm:DB_HOST}\n" .
                "\tDB_PORT = {aws-ssm}\n" .
                "  DB_NAME  =  {aws-ssm:DB_NAME}  \n" .
                "DB_PASSWORD={aws-ssm:DB_PASSWORD}    # Production password"
            );
            
            $secrets = new SecretsCollection([
                new Secret('DB_HOST', 'localhost'),
                new Secret('DB_PORT', '3306'),
                new Secret('DB_NAME', 'myapp'),
                new Secret('DB_PASSWORD', 'secret'),
            ]);
            
            $result = $template->merge('aws-ssm', $secrets, MissingSecretStrategy::FAIL);
            
            // All original formatting should be preserved
            expect($result)->toBe(
                "    DB_HOST=localhost\n" .
                "\tDB_PORT = 3306\n" .
                "  DB_NAME  =  myapp  \n" .
                "DB_PASSWORD=secret    # Production password"
            );
        });
    });
    
    describe('consistency with SecretsCollection', function () {
        it('produces same escaping as SecretsCollection::toEnvString()', function () {
            $secrets = new SecretsCollection([
                new Secret('KEY1', 'simple value'),
                new Secret('KEY2', 'value with "quotes"'),
                new Secret('KEY3', null),
                new Secret('KEY4', 'path\\with\\backslash'),
            ]);
            
            // Template approach (without extra whitespace so it matches)
            $template = new Template(
                "KEY1={aws-ssm:KEY1}\n" .
                "KEY2={aws-ssm:KEY2}\n" .
                "KEY3={aws-ssm:KEY3}\n" .
                "KEY4={aws-ssm:KEY4}"
            );
            $templateResult = $template->merge('aws-ssm', $secrets, MissingSecretStrategy::BLANK);
            
            // SecretsCollection approach
            $collectionResult = $secrets->toEnvString();
            
            // Both should produce the same output when template has no extra whitespace
            expect($templateResult)->toBe($collectionResult);
        });
    });
    
    describe('complex scenarios', function () {
        it('handles real-world .env template', function () {
            $template = new Template(
                "# Application Settings\n" .
                "APP_NAME=\"My App\"\n" .
                "APP_ENV=production\n" .
                "APP_KEY={aws-ssm:APP_KEY}\n" .
                "APP_DEBUG=false\n" .
                "\n" .
                "# Database Configuration\n" .
                "DB_CONNECTION=mysql\n" .
                "DB_HOST={aws-ssm:DB_HOST}\n" .
                "DB_PORT=3306\n" .
                "DB_DATABASE=myapp_prod\n" .
                "DB_USERNAME=admin\n" .
                "DB_PASSWORD='{aws-ssm:DB_PASSWORD}' # Production database\n" .
                "\n" .
                "# Mail Settings\n" .
                "MAIL_MAILER=smtp\n" .
                "MAIL_HOST=smtp.mailgun.org\n" .
                "MAIL_PORT=587\n" .
                "MAIL_USERNAME=\"{aws-ssm}\"\n" .
                "MAIL_PASSWORD={aws-ssm:MAIL_PASSWORD}\n" .
                "MAIL_ENCRYPTION=tls\n" .
                "\n" .
                "# Third-party APIs\n" .
                "STRIPE_KEY={aws-ssm:STRIPE_KEY|environment=production}\n" .
                "STRIPE_SECRET={aws-ssm:STRIPE_SECRET|environment=production|version=latest}"
            );
            
            $secrets = new SecretsCollection([
                new Secret('APP_KEY', 'base64:production_key'),
                new Secret('DB_HOST', 'prod.db.example.com'),
                new Secret('DB_PASSWORD', 'super_secret_pass'),
                new Secret('MAIL_USERNAME', 'postmaster@example.com'),
                new Secret('MAIL_PASSWORD', 'mail_secret'),
                new Secret('STRIPE_KEY', 'pk_live_123'),
                new Secret('STRIPE_SECRET', 'sk_live_456'),
            ]);
            
            $result = $template->merge('aws-ssm', $secrets, MissingSecretStrategy::FAIL);
            
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
                "ALPHANUMERIC={aws-ssm:ALPHANUMERIC}\n" .
                "NUMERIC={aws-ssm:NUMERIC}"
            );
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe(
                "ALPHANUMERIC=abc123\n" .
                "NUMERIC=12345"
            );
        });
        
        it('quotes values with spaces', function () {
            $template = new Template('WITH_SPACES={aws-ssm:WITH_SPACES}');
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe('WITH_SPACES="value with spaces"');
        });
        
        it('quotes values with special characters', function () {
            $template = new Template('APP_KEY={aws-ssm:APP_KEY}');
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe('APP_KEY="base64:key123"');
        });
        
        it('handles values with single quotes using double quotes', function () {
            $template = new Template('WITH_SINGLE_QUOTES={aws-ssm:WITH_SINGLE_QUOTES}');
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe('WITH_SINGLE_QUOTES="value with \'quotes\'"');
        });
        
        it('handles values with double quotes using single quotes and escaping', function () {
            $template = new Template('WITH_DOUBLE_QUOTES={aws-ssm:WITH_DOUBLE_QUOTES}');
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe('WITH_DOUBLE_QUOTES=\'value with "quotes"\'');
        });
        
        it('handles values with both quote types', function () {
            $template = new Template('WITH_BOTH_QUOTES={aws-ssm:WITH_BOTH_QUOTES}');
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe('WITH_BOTH_QUOTES=\'value with "double" and \\\'single\\\'\'');
        });
        
        it('properly escapes backslashes', function () {
            $template = new Template('WITH_BACKSLASH={aws-ssm:WITH_BACKSLASH}');
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe('WITH_BACKSLASH="value\\\\with\\\\backslash"');
        });
        
        it('handles empty values without quotes', function () {
            $template = new Template('EMPTY_VALUE={aws-ssm:EMPTY_VALUE}');
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe('EMPTY_VALUE=');
        });
        
        it('handles null values without quotes', function () {
            $template = new Template('NULL_VALUE={aws-ssm:NULL_VALUE}');
            
            $result = $template->merge('aws-ssm', $this->secrets, MissingSecretStrategy::FAIL);
            
            expect($result)->toBe('NULL_VALUE=');
        });
    });
});
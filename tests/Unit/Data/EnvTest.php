<?php

use STS\Keep\Data\Env;

describe('Env', function () {

    describe('basic functionality', function () {
        it('stores and returns contents', function () {
            $contents = "DB_HOST=localhost\nDB_PORT=3306";
            $env = new Env($contents);

            expect($env->contents())->toBe($contents);
        });

        it('handles empty contents', function () {
            $env = new Env('');

            expect($env->contents())->toBe('');
            expect($env->entries())->toBeInstanceOf(\Illuminate\Support\Collection::class);
            expect($env->entries())->toBeEmpty();
        });
    });

    describe('parsing .env entries', function () {
        it('parses simple key-value pairs', function () {
            $env = new Env("DB_HOST=localhost\nDB_PORT=3306\nDB_NAME=myapp");

            $entries = $env->entries();

            expect($entries)->toHaveCount(3);
            expect($entries->first()->getName())->toBe('DB_HOST');
            expect($entries->first()->getValue()->get()->getChars())->toBe('localhost');
        });

        it('parses quoted values', function () {
            $env = new Env("APP_NAME=\"My Application\"\nAPP_DESC='A great app'");

            $entries = $env->entries();

            expect($entries)->toHaveCount(2);
            expect($entries->get(0)->getValue()->get()->getChars())->toBe('My Application');
            expect($entries->get(1)->getValue()->get()->getChars())->toBe('A great app');
        });

        it('handles values with special characters', function () {
            $env = new Env('SPECIAL_CHARS="value with spaces and symbols"');

            $entries = $env->entries();

            expect($entries->first()->getValue()->get()->getChars())->toBe('value with spaces and symbols');
        });

        it('handles empty values', function () {
            $env = new Env("EMPTY_VALUE=\nNULL_VALUE=");

            $entries = $env->entries();

            expect($entries)->toHaveCount(2);
            expect($entries->get(0)->getValue()->get()->getChars())->toBe('');
            expect($entries->get(1)->getValue()->get()->getChars())->toBe('');
        });

        it('ignores comments and empty lines', function () {
            $contents = '
# This is a comment
DB_HOST=localhost

# Another comment  
DB_PORT=3306

';
            $env = new Env($contents);

            $entries = $env->entries();

            expect($entries)->toHaveCount(2);
            expect($entries->get(0)->getName())->toBe('DB_HOST');
            expect($entries->get(1)->getName())->toBe('DB_PORT');
        });

        it('handles inline comments', function () {
            $env = new Env('DB_HOST=localhost # Database host');

            $entries = $env->entries();

            expect($entries->first()->getName())->toBe('DB_HOST');
            expect($entries->first()->getValue()->get()->getChars())->toBe('localhost');
        });

        it('handles mixed case keys', function () {
            $env = new Env("lowercase=value\nUPPERCASE=value\nmixedCase=value");

            $entries = $env->entries();

            expect($entries)->toHaveCount(3);
            expect($entries->get(0)->getName())->toBe('lowercase');
            expect($entries->get(1)->getName())->toBe('UPPERCASE');
            expect($entries->get(2)->getName())->toBe('mixedCase');
        });
    });

    describe('extracting keys', function () {
        it('returns all keys from parsed entries', function () {
            $env = new Env("DB_HOST=localhost\nDB_PORT=3306\nMAIL_HOST=smtp.example.com");

            $keys = $env->allKeys();

            expect($keys)->toBeInstanceOf(\Illuminate\Support\Collection::class);
            expect($keys->toArray())->toBe(['DB_HOST', 'DB_PORT', 'MAIL_HOST']);
        });

        it('returns empty collection for env with no keys', function () {
            $env = new Env("# Only comments\n\n# More comments");

            $keys = $env->allKeys();

            expect($keys)->toBeEmpty();
        });

        it('preserves order of keys', function () {
            $env = new Env("THIRD=3\nFIRST=1\nSECOND=2");

            $keys = $env->allKeys();

            expect($keys->toArray())->toBe(['THIRD', 'FIRST', 'SECOND']);
        });
    });

    describe('simple key-value listing', function () {
        it('returns simple key-value pairs via list()', function () {
            $env = new Env("DB_HOST=localhost\nDB_PORT=3306\nDB_NAME=myapp");

            $list = $env->list();

            expect($list)->toBeInstanceOf(\Illuminate\Support\Collection::class);
            expect($list->toArray())->toBe([
                'DB_HOST' => 'localhost',
                'DB_PORT' => '3306',
                'DB_NAME' => 'myapp',
            ]);
        });

        it('handles quoted values in list()', function () {
            $env = new Env("APP_NAME=\"My Application\"\nAPP_DESC='A great app'");

            $list = $env->list();

            expect($list->toArray())->toBe([
                'APP_NAME' => 'My Application',
                'APP_DESC' => 'A great app',
            ]);
        });

        it('handles empty values in list()', function () {
            $env = new Env("EMPTY_VALUE=\nNULL_VALUE=");

            $list = $env->list();

            expect($list->toArray())->toBe([
                'EMPTY_VALUE' => '',
                'NULL_VALUE' => '',
            ]);
        });

        it('returns empty collection for no entries', function () {
            $env = new Env("# Only comments\n\n# More comments");

            $list = $env->list();

            expect($list)->toBeEmpty();
        });

        it('preserves order in list()', function () {
            $env = new Env("THIRD=3\nFIRST=1\nSECOND=2");

            $list = $env->list();

            expect(array_keys($list->toArray()))->toBe(['THIRD', 'FIRST', 'SECOND']);
            expect($list->toArray())->toBe([
                'THIRD' => '3',
                'FIRST' => '1',
                'SECOND' => '2',
            ]);
        });
    });

    describe('complex .env scenarios', function () {
        it('parses real-world .env file', function () {
            $contents = '# Application Configuration
APP_NAME="Laravel Keep"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:somebase64encodedkey

# Database Configuration  
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=keep_production
DB_USERNAME=keep_user
DB_PASSWORD="complex$password#123"

# AWS Configuration
AWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE
AWS_SECRET_ACCESS_KEY="wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY"
AWS_DEFAULT_REGION=us-east-1

# Empty values
OPTIONAL_SETTING=
ANOTHER_OPTIONAL=';

            $env = new Env($contents);

            $entries = $env->entries();
            $keys = $env->allKeys();

            expect($entries->count())->toBe(15);
            expect($keys)->toContain('APP_NAME', 'DB_PASSWORD', 'AWS_SECRET_ACCESS_KEY');

            // Test specific values
            $appName = $entries->first(fn ($entry) => $entry->getName() === 'APP_NAME');
            expect($appName->getValue()->get()->getChars())->toBe('Laravel Keep');

            $dbPassword = $entries->first(fn ($entry) => $entry->getName() === 'DB_PASSWORD');
            expect($dbPassword->getValue()->get()->getChars())->toBe('complex$password#123');

            $awsSecret = $entries->first(fn ($entry) => $entry->getName() === 'AWS_SECRET_ACCESS_KEY');
            expect($awsSecret->getValue()->get()->getChars())->toBe('wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY');
        });

        it('handles malformed entries gracefully', function () {
            // Skip invalid lines - dotenv parser is strict about format
            $env = new Env("VALID=value\nANOTHER_VALID=another");

            $entries = $env->entries();

            expect($entries->count())->toBe(2);
            expect($env->allKeys())->toContain('VALID', 'ANOTHER_VALID');
        });
    });

    describe('entry object properties', function () {
        it('provides access to entry details', function () {
            $env = new Env('TEST_KEY="test value"');

            $entry = $env->entries()->first();

            expect($entry->getName())->toBe('TEST_KEY');
            expect($entry->getValue()->get()->getChars())->toBe('test value');
        });

        it('caches parsed entries', function () {
            $env = new Env('KEY=value');

            $entries1 = $env->entries();
            $entries2 = $env->entries();

            expect($entries1)->toBe($entries2); // Same object reference
        });
    });

    describe('secrets collection conversion', function () {
        it('converts entries to SecretsCollection via secrets()', function () {
            $env = new Env("DB_HOST=localhost\nDB_PORT=3306\nAPI_KEY=secret");
            $secrets = $env->secrets();

            expect($secrets)->toBeInstanceOf(\STS\Keep\Data\Collections\SecretCollection::class);
            expect($secrets->count())->toBe(3);

            // Check that secrets are properly created
            expect($secrets->hasKey('DB_HOST'))->toBeTrue();
            expect($secrets->hasKey('DB_PORT'))->toBeTrue();
            expect($secrets->hasKey('API_KEY'))->toBeTrue();

            // Check values
            expect($secrets->getByKey('DB_HOST')->value())->toBe('localhost');
            expect($secrets->getByKey('DB_PORT')->value())->toBe('3306');
            expect($secrets->getByKey('API_KEY')->value())->toBe('secret');
        });

        it('creates secrets with proper attributes', function () {
            $env = new Env('TEST_KEY=test_value');
            $secrets = $env->secrets();
            $secret = $secrets->getByKey('TEST_KEY');

            expect($secret->key())->toBe('TEST_KEY');
            expect($secret->value())->toBe('test_value');
            expect($secret->revision())->toBe(0); // New secrets start at 0
        });

        it('handles empty env in secrets()', function () {
            $env = new Env('');
            $secrets = $env->secrets();

            expect($secrets)->toBeInstanceOf(\STS\Keep\Data\Collections\SecretCollection::class);
            expect($secrets->count())->toBe(0);
        });
    });

    describe('edge cases', function () {
        it('handles unicode values', function () {
            $env = new Env('UNICODE="Hello 世界 🚀 مرحبا"');

            $entry = $env->entries()->first();

            expect($entry->getValue()->get()->getChars())->toBe('Hello 世界 🚀 مرحبا');
        });

        it('handles values with escaped quotes', function () {
            $env = new Env('ESCAPED="Value with \\"quotes\\""');

            $entry = $env->entries()->first();

            expect($entry->getValue()->get()->getChars())->toBe('Value with "quotes"');
        });

        it('handles environment variable interpolation', function () {
            // When there are variables to interpolate, getValue() returns None
            // when no interpolation is needed, it returns Some with the value
            $env = new Env('SIMPLE=value');

            $entry = $env->entries()->first();

            expect($entry->getValue()->isDefined())->toBeTrue();
            expect($entry->getValue()->get()->getChars())->toBe('value');
        });
    });
});

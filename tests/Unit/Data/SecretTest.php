<?php

use STS\Keep\Data\Secret;
use STS\Keep\Vaults\AbstractVault;

describe('Secret', function () {

    describe('key validation', function () {
        it('accepts valid keys', function ($key) {
            $secret = new Secret($key, 'value');
            expect($secret->key())->toBe($key);
        })->with([
            'simple uppercase' => ['DB_HOST'],
            'simple lowercase' => ['api_key'],
            'mixed case' => ['MyAppKey'],
            'with numbers' => ['API_KEY_V2'],
            'numbers in middle' => ['APP_123_KEY'],
            'multiple underscores' => ['LONG_SECRET_KEY_NAME'],
            'ending with number' => ['DATABASE_PORT_3306'],
        ]);

        it('trims whitespace from valid keys', function () {
            $secret = new Secret('  DB_HOST  ', 'value');
            expect($secret->key())->toBe('DB_HOST');
        });

        it('rejects keys with spaces', function ($key) {
            expect(fn () => new Secret($key, 'value'))
                ->toThrow(\InvalidArgumentException::class, "contains invalid characters");
        })->with([
            'single space' => ['DB HOST'],
            'multiple spaces' => ['MY API KEY'],
        ]);

        it('rejects keys with hyphens', function ($key) {
            expect(fn () => new Secret($key, 'value'))
                ->toThrow(\InvalidArgumentException::class, "contains invalid characters");
        })->with([
            'single hyphen' => ['my-api-key'],
            'multiple hyphens' => ['user-email-address'],
            'hyphen and underscore' => ['app-config_key'],
        ]);

        it('rejects keys with special characters', function ($key) {
            expect(fn () => new Secret($key, 'value'))
                ->toThrow(\InvalidArgumentException::class, "contains invalid characters");
        })->with([
            'dot notation' => ['user.email'],
            'slash separator' => ['app/config'],
            'path traversal' => ['../secret'],
            'control characters' => ["DB\x00HOST"],
            'unicode' => ['APP_名前'],
            'emoji' => ['API_🔑'],
            'special symbols' => ['key@domain'],
            'parentheses' => ['key(test)'],
            'brackets' => ['key[0]'],
            'braces' => ['key{test}'],
            'percent' => ['key%value'],
            'ampersand' => ['key&value'],
            'asterisk' => ['key*value'],
            'plus' => ['key+value'],
            'equals' => ['key=value'],
            'question' => ['key?value'],
            'exclamation' => ['key!value'],
            'colon' => ['key:value'],
            'semicolon' => ['key;value'],
            'comma' => ['key,value'],
            'less than' => ['key<value'],
            'greater than' => ['key>value'],
            'pipe' => ['key|value'],
            'backslash' => ['key\\value'],
            'quotes' => ['key"value'],
            'apostrophe' => ["key'value"],
            'backtick' => ['key`value'],
            'tilde' => ['key~value'],
        ]);

        it('rejects keys starting with underscore', function ($key) {
            expect(fn () => new Secret($key, 'value'))
                ->toThrow(\InvalidArgumentException::class, "cannot start with underscore");
        })->with([
            'single underscore' => ['_SECRET'],
            'multiple underscores' => ['__PRIVATE'],
            'underscore with valid chars' => ['_DB_HOST'],
        ]);

        it('rejects keys starting with numbers', function ($key) {
            expect(fn () => new Secret($key, 'value'))
                ->toThrow(\InvalidArgumentException::class, "cannot start with a number");
        })->with([
            'single digit' => ['1KEY'],
            'multiple digits' => ['123_SECRET'],
            'number with underscore' => ['1_API_KEY'],
        ]);

        it('rejects empty or too short keys', function ($key) {
            expect(fn () => new Secret($key, 'value'))
                ->toThrow(\InvalidArgumentException::class);
        })->with([
            'empty string' => [''],
            'only spaces' => ['   '],
            'only tabs' => ["\t\t"],
            'only newlines' => ["\n\n"],
        ]);

        it('rejects keys that are too long', function () {
            $longKey = str_repeat('A', 256); // 256 characters
            expect(fn () => new Secret($longKey, 'value'))
                ->toThrow(\InvalidArgumentException::class, "must be 1-255 characters long");
        });

        it('accepts maximum length keys', function () {
            $maxKey = str_repeat('A', 255); // 255 characters
            $secret = new Secret($maxKey, 'value');
            expect($secret->key())->toBe($maxKey);
        });
    });

    it('can be created with minimal parameters', function () {
        $secret = new Secret(
            key: 'DB_PASSWORD',
            value: 'secret123'
        );

        expect($secret->key())->toBe('DB_PASSWORD');
        expect($secret->value())->toBe('secret123');
        expect($secret->isSecure())->toBeTrue();
        expect($secret->stage())->toBeNull();
        expect($secret->revision())->toBe(0);
        expect($secret->path())->toBeNull();
        expect($secret->vault())->toBeNull();
    });

    it('can be created with all parameters', function () {
        $mockVault = Mockery::mock(AbstractVault::class);
        $mockVault->shouldReceive('name')->andReturn('test-vault');

        $secret = new Secret(
            key: 'API_KEY',
            value: 'plaintext',
            encryptedValue: 'encrypted',
            secure: false,
            stage: 'production',
            revision: 3,
            path: '/app/production/API_KEY',
            vault: $mockVault
        );

        expect($secret->key())->toBe('API_KEY');
        expect($secret->value())->toBe('plaintext');
        expect($secret->encryptedValue())->toBe('encrypted');
        expect($secret->isSecure())->toBeFalse();
        expect($secret->stage())->toBe('production');
        expect($secret->revision())->toBe(3);
        expect($secret->path())->toBe('/app/production/API_KEY');
        expect($secret->vault())->toBe($mockVault);
    });

    it('handles null values correctly', function () {
        $secret = new Secret(
            key: 'EMPTY_KEY',
            value: null
        );

        expect($secret->key())->toBe('EMPTY_KEY');
        expect($secret->value())->toBeNull();
        expect($secret->encryptedValue())->toBeNull();
    });

    it('converts to array correctly', function () {
        $mockVault = Mockery::mock(AbstractVault::class);
        $mockVault->shouldReceive('name')->andReturn('test-vault');

        $secret = new Secret(
            key: 'DB_HOST',
            value: 'localhost',
            encryptedValue: 'encrypted_localhost',
            secure: true,
            stage: 'staging',
            revision: 2,
            path: '/app/staging/DB_HOST',
            vault: $mockVault
        );

        $array = $secret->toArray();

        expect($array)->toBe([
            'key' => 'DB_HOST',
            'value' => 'localhost',
            'encryptedValue' => 'encrypted_localhost',
            'secure' => true,
            'stage' => 'staging',
            'revision' => 2,
            'path' => '/app/staging/DB_HOST',
            'vault' => 'test-vault',
        ]);
    });

    it('converts to array without vault', function () {
        $secret = new Secret(
            key: 'DB_NAME',
            value: 'myapp'
        );

        $array = $secret->toArray();

        expect($array['vault'])->toBeNull();
    });

    it('can filter array output with only()', function () {
        $secret = new Secret(
            key: 'APP_KEY',
            value: 'base64_key',
            secure: true,
            stage: 'production',
            revision: 1
        );

        $filtered = $secret->only(['key', 'value']);

        expect($filtered)->toBeArray();
        expect($filtered)->toHaveCount(2);
        expect($filtered)->toHaveKeys(['key', 'value']);
        expect($filtered['key'])->toBe('APP_KEY');
        expect($filtered['value'])->toBe('base64_key');
    });

    it('handles empty only() filter', function () {
        $secret = new Secret(
            key: 'TEST_KEY',
            value: 'test_value'
        );

        $filtered = $secret->only([]);

        expect($filtered)->toBeArray();
        expect($filtered)->toBeEmpty();
    });

    it('handles special characters in values', function () {
        $specialValue = "Special!@#$%^&*()_+{}|:\"<>?[];',./`~\n\t";
        $secret = new Secret(
            key: 'SPECIAL_CHARS',
            value: $specialValue
        );

        expect($secret->value())->toBe($specialValue);
        expect($secret->toArray()['value'])->toBe($specialValue);
    });

    it('handles unicode and emoji in values', function () {
        $unicodeValue = 'Hello 世界 🚀 مرحبا';
        $secret = new Secret(
            key: 'UNICODE_TEST',
            value: $unicodeValue
        );

        expect($secret->value())->toBe($unicodeValue);
        expect($secret->toArray()['value'])->toBe($unicodeValue);
    });

    it('handles very long keys and values', function () {
        $longKey = str_repeat('LONG_KEY_', 28) . 'END'; // 255 characters (at 255 limit)
        $longValue = str_repeat('This is a very long value. ', 1000);

        $secret = new Secret(
            key: $longKey,
            value: $longValue
        );

        expect($secret->key())->toBe($longKey);
        expect($secret->value())->toBe($longValue);
        expect(strlen($secret->key()))->toBe(255);
        expect(strlen($secret->value()))->toBe(27000);
    });

    it('maintains secure flag default as true', function () {
        $secret = new Secret(
            key: 'SECURE_BY_DEFAULT',
            value: 'password'
        );

        expect($secret->isSecure())->toBeTrue();
    });

    it('allows explicitly setting secure to false', function () {
        $secret = new Secret(
            key: 'NOT_SECURE',
            value: 'public_value',
            secure: false
        );

        expect($secret->isSecure())->toBeFalse();
    });

    it('handles encrypted value without plain value', function () {
        $secret = new Secret(
            key: 'ENCRYPTED_ONLY',
            value: null,
            encryptedValue: 'encrypted_data'
        );

        expect($secret->value())->toBeNull();
        expect($secret->encryptedValue())->toBe('encrypted_data');
    });

    it('handles revision number edge cases', function () {
        $secret1 = new Secret(
            key: 'REV_TEST_0',
            value: 'test',
            revision: 0
        );

        $secret2 = new Secret(
            key: 'REV_TEST_MAX',
            value: 'test',
            revision: 999999
        );

        $secret3 = new Secret(
            key: 'REV_TEST_NULL',
            value: 'test',
            revision: null
        );

        expect($secret1->revision())->toBe(0);
        expect($secret2->revision())->toBe(999999);
        expect($secret3->revision())->toBeNull();
    });

    it('properly implements Arrayable interface', function () {
        $secret = new Secret(
            key: 'INTERFACE_TEST',
            value: 'test'
        );

        expect($secret)->toBeInstanceOf(\Illuminate\Contracts\Support\Arrayable::class);
        expect($secret->toArray())->toBeArray();
    });

    describe('masked() method', function () {
        it('returns null for null values', function () {
            $secret = new Secret(
                key: 'NULL_VALUE',
                value: null
            );

            expect($secret->masked())->toBeNull();
        });

        it('masks short values (≤8 chars) with ****', function () {
            $testCases = [
                'a' => '****',
                'ab' => '****',
                'abc' => '****',
                'abcd' => '****',
                'abcde' => '****',
                'abcdef' => '****',
                'abcdefg' => '****',
                'abcdefgh' => '****',
            ];

            foreach ($testCases as $value => $expected) {
                $secret = new Secret(key: 'SHORT_VALUE', value: $value);
                expect($secret->masked())->toBe($expected, "Value '$value' should be masked as '$expected'");
            }
        });

        it('masks longer values (>8 chars) with first 4 chars + asterisks', function () {
            $testCases = [
                'abcdefghi' => 'abcd*****',
                'localhost' => 'loca*****',
                'secret_api_key' => 'secr**********',
                'smtp_example_com' => 'smtp************',
                'very_long_password_value' => 'very********************',
            ];

            foreach ($testCases as $value => $expected) {
                $secret = new Secret(key: 'LONG_VALUE', value: $value);
                expect($secret->masked())->toBe($expected, "Value '$value' should be masked as '$expected'");
            }
        });

        it('handles empty string', function () {
            $secret = new Secret(
                key: 'EMPTY_VALUE',
                value: ''
            );

            expect($secret->masked())->toBe('****');
        });

        it('handles unicode characters in masking', function () {
            $value = 'Hello_world_test_unicode';
            $secret = new Secret(
                key: 'UNICODE_VALUE',
                value: $value
            );

            // Calculate expected result dynamically
            $length = strlen($value);
            $expectedAsterisks = $length - 4;
            $expected = 'Hell'.str_repeat('*', $expectedAsterisks);

            expect($secret->masked())->toBe($expected);
            expect($length)->toBeGreaterThan(8); // Ensure it's using the long value logic
        });

        it('handles special characters in masking', function () {
            $secret = new Secret(
                key: 'SPECIAL_VALUE',
                value: 'value with & symbols!'
            );

            expect($secret->masked())->toBe('valu*****************');
        });
    });
});
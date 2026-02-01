<?php

use STS\Keep\Validation\SecretKeyValidator;

describe('SecretKeyValidator', function () {
    beforeEach(function () {
        $this->validator = new SecretKeyValidator();
    });

    describe('validate()', function () {
        it('accepts valid keys', function ($key) {
            expect($this->validator->validate($key))->toBe(trim($key));
        })->with([
            'simple uppercase' => ['DATABASE_PASSWORD'],
            'simple lowercase' => ['database_password'],
            'mixed case' => ['Database_Password'],
            'with numbers' => ['DB_PASSWORD_2'],
            'numbers in middle' => ['API_V2_KEY'],
            'multiple underscores' => ['MY__SUPER__SECRET'],
            'ending with number' => ['SECRET_123'],
            'with hyphen' => ['my-secret-key'],
            'mixed hyphen underscore' => ['my_secret-key'],
            'single character' => ['A'],
            'all numbers after start' => ['X123456'],
        ]);

        it('trims whitespace from valid keys', function () {
            expect($this->validator->validate('  API_KEY  '))->toBe('API_KEY');
        });

        it('rejects keys with spaces', function ($key) {
            expect(fn() => $this->validator->validate($key))
                ->toThrow(\InvalidArgumentException::class);
        })->with([
            'single space' => ['API KEY'],
            'multiple spaces' => ['API  KEY  NAME'],
        ]);

        it('rejects keys with special characters', function ($key) {
            expect(fn() => $this->validator->validate($key))
                ->toThrow(\InvalidArgumentException::class);
        })->with([
            'dot notation' => ['api.key'],
            'slash separator' => ['api/key'],
            'path traversal' => ['../../../etc/passwd'],
            'control characters' => ["key\x00name"],
            'unicode' => ['键名'],
            'at symbol' => ['user@domain'],
            'equals sign' => ['key=value'],
            'colon' => ['vault:key'],
        ]);

        it('rejects keys starting with hyphen', function () {
            expect(fn() => $this->validator->validate('-my-key'))
                ->toThrow(\InvalidArgumentException::class, 'cannot start with a hyphen');
        });

        it('rejects empty keys', function () {
            expect(fn() => $this->validator->validate(''))
                ->toThrow(\InvalidArgumentException::class);
        });

        it('rejects keys that are too long', function () {
            $longKey = str_repeat('A', 256);
            expect(fn() => $this->validator->validate($longKey))
                ->toThrow(\InvalidArgumentException::class, '1-255 characters');
        });

        it('accepts maximum length keys', function () {
            $maxKey = str_repeat('A', 255);
            expect($this->validator->validate($maxKey))->toBe($maxKey);
        });
    });

    describe('isValid()', function () {
        it('returns true for valid keys', function () {
            expect($this->validator->isValid('API_KEY'))->toBeTrue();
            expect($this->validator->isValid('my-secret'))->toBeTrue();
        });

        it('returns false for invalid keys', function () {
            expect($this->validator->isValid('api.key'))->toBeFalse();
            expect($this->validator->isValid('-starts-with-hyphen'))->toBeFalse();
            expect($this->validator->isValid(''))->toBeFalse();
        });
    });

    describe('getValidationError()', function () {
        it('returns null for valid keys', function () {
            expect($this->validator->getValidationError('API_KEY'))->toBeNull();
        });

        it('returns error message for invalid keys', function () {
            expect($this->validator->getValidationError('api.key'))->toContain('invalid characters');
            expect($this->validator->getValidationError('-key'))->toContain('hyphen');
        });
    });

    describe('getValidationRules()', function () {
        it('returns validation rules for frontend', function () {
            $rules = $this->validator->getValidationRules();

            expect($rules)->toHaveKey('minLength');
            expect($rules)->toHaveKey('maxLength');
            expect($rules)->toHaveKey('pattern');
            expect($rules)->toHaveKey('patternDescription');
            expect($rules)->toHaveKey('noLeadingHyphen');

            expect($rules['minLength'])->toBe(1);
            expect($rules['maxLength'])->toBe(255);
        });
    });
});

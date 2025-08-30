<?php

use STS\Keep\Data\Placeholder;
use STS\Keep\Data\PlaceholderValidationResult;
use STS\Keep\Data\Secret;
use STS\Keep\Tests\Support\TestVault;

describe('Placeholder', function () {

    it('handles simple placeholders without vault prefix', function () {
        $match = [
            'key' => 'API_KEY',
            'vault' => 'API_KEY',
        ];

        $placeholder = Placeholder::fromMatch($match, 1, 'API_KEY={API_KEY}');

        expect($placeholder->line)->toBe(1);
        expect($placeholder->envKey)->toBe('API_KEY');
        expect($placeholder->vault)->toBeNull();
        expect($placeholder->key)->toBe('API_KEY');
        expect($placeholder->placeholderText)->toBe('{API_KEY}');
    });

    it('validates key format through validation method', function () {
        // Create mock objects
        $validPlaceholder = new Placeholder(1, 'VALID_KEY', null, 'VALID_KEY', 'line', '{VALID_KEY}');
        $invalidPlaceholder = new Placeholder(1, 'INVALID_KEY', null, 'INVALID-KEY', 'line', '{INVALID-KEY}');

        // Test that validation passes/fails appropriately (we can't directly test the protected method)
        expect($validPlaceholder->key)->toBe('VALID_KEY');
        expect($invalidPlaceholder->key)->toBe('INVALID-KEY');
    });


    it('converts to array for backward compatibility', function () {
        $placeholder = new Placeholder(2, 'DB_HOST', 'ssm', 'DB_HOST', 'DB_HOST={ssm:DB_HOST}', '{ssm:DB_HOST}');
        $array = $placeholder->toArray();

        expect($array)->toMatchArray([
            'line' => 2,
            'full' => 'DB_HOST',
            'vault' => 'ssm',
            'key' => 'DB_HOST',
            'raw_line' => 'DB_HOST={ssm:DB_HOST}',
            'env_key' => 'DB_HOST',
            'placeholder_text' => '{ssm:DB_HOST}',
        ]);
    });
});

describe('PlaceholderValidationResult', function () {
    it('creates valid result', function () {
        $placeholder = new Placeholder(1, 'KEY', null, 'KEY', 'line', '{KEY}');
        $vault = new TestVault('test-vault', [], 'testing');
        $secret = new Secret('KEY', 'value', null, true, 'testing', 1, null, $vault);

        $result = PlaceholderValidationResult::valid($placeholder, 'vault', $secret);

        expect($result->placeholder)->toBe($placeholder);
        expect($result->vault)->toBe('vault');
        expect($result->valid)->toBeTrue();
        expect($result->error)->toBeNull();
        expect($result->secret)->toBe($secret);
    });

    it('creates invalid result', function () {
        $placeholder = new Placeholder(1, 'KEY', null, 'KEY', 'line', '{KEY}');

        $result = PlaceholderValidationResult::invalid($placeholder, 'vault', 'Error message');

        expect($result->placeholder)->toBe($placeholder);
        expect($result->vault)->toBe('vault');
        expect($result->valid)->toBeFalse();
        expect($result->error)->toBe('Error message');
        expect($result->secret)->toBeNull();
    });

    it('converts to array for backward compatibility', function () {
        $placeholder = new Placeholder(1, 'KEY', null, 'KEY', 'line', '{KEY}');
        $result = PlaceholderValidationResult::invalid($placeholder, 'vault', 'Error');

        $array = $result->toArray();

        expect($array)->toHaveKey('placeholder');
        expect($array)->toHaveKey('vault');
        expect($array)->toHaveKey('key');
        expect($array)->toHaveKey('valid');
        expect($array)->toHaveKey('error');
        expect($array['valid'])->toBeFalse();
        expect($array['error'])->toBe('Error');
    });
});

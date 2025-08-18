<?php

use STS\Keep\Data\Secret;
use STS\Keep\Vaults\AbstractVault;

beforeEach(function () {
    $this->mockVault = Mockery::mock(AbstractVault::class);
    $this->mockVault->shouldReceive('name')->andReturn('test-vault');
});

describe('Secret', function () {
    
    it('can be created with minimal parameters', function () {
        $secret = new Secret(
            key: 'DB_PASSWORD',
            value: 'secret123'
        );
        
        expect($secret->key())->toBe('DB_PASSWORD');
        expect($secret->value())->toBe('secret123');
        expect($secret->isSecure())->toBeTrue();
        expect($secret->environment())->toBeNull();
        expect($secret->revision())->toBe(0);
        expect($secret->path())->toBeNull();
        expect($secret->vault())->toBeNull();
    });
    
    it('can be created with all parameters', function () {
        $secret = new Secret(
            key: 'API_KEY',
            value: 'plaintext',
            encryptedValue: 'encrypted',
            secure: false,
            environment: 'production',
            revision: 3,
            path: '/app/production/API_KEY',
            vault: $this->mockVault
        );
        
        expect($secret->key())->toBe('API_KEY');
        expect($secret->value())->toBe('plaintext');
        expect($secret->encryptedValue())->toBe('encrypted');
        expect($secret->isSecure())->toBeFalse();
        expect($secret->environment())->toBe('production');
        expect($secret->revision())->toBe(3);
        expect($secret->path())->toBe('/app/production/API_KEY');
        expect($secret->vault())->toBe($this->mockVault);
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
        $secret = new Secret(
            key: 'DB_HOST',
            value: 'localhost',
            encryptedValue: 'encrypted_localhost',
            secure: true,
            environment: 'staging',
            revision: 2,
            path: '/app/staging/DB_HOST',
            vault: $this->mockVault
        );
        
        $array = $secret->toArray();
        
        expect($array)->toBeArray();
        expect($array)->toHaveKeys(['key', 'value', 'encryptedValue', 'secure', 'environment', 'revision', 'path', 'vault']);
        expect($array['key'])->toBe('DB_HOST');
        expect($array['value'])->toBe('localhost');
        expect($array['encryptedValue'])->toBe('encrypted_localhost');
        expect($array['secure'])->toBeTrue();
        expect($array['environment'])->toBe('staging');
        expect($array['revision'])->toBe(2);
        expect($array['path'])->toBe('/app/staging/DB_HOST');
        expect($array['vault'])->toBe('test-vault');
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
            value: 'base64:key',
            secure: true,
            environment: 'production',
            revision: 1
        );
        
        $filtered = $secret->only(['key', 'value']);
        
        expect($filtered)->toBeArray();
        expect($filtered)->toHaveCount(2);
        expect($filtered)->toHaveKeys(['key', 'value']);
        expect($filtered['key'])->toBe('APP_KEY');
        expect($filtered['value'])->toBe('base64:key');
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
        $unicodeValue = "Hello 世界 🚀 مرحبا";
        $secret = new Secret(
            key: 'UNICODE_TEST',
            value: $unicodeValue
        );
        
        expect($secret->value())->toBe($unicodeValue);
        expect($secret->toArray()['value'])->toBe($unicodeValue);
    });
    
    it('handles very long keys and values', function () {
        $longKey = str_repeat('LONG_KEY_', 100);
        $longValue = str_repeat('This is a very long value. ', 1000);
        
        $secret = new Secret(
            key: $longKey,
            value: $longValue
        );
        
        expect($secret->key())->toBe($longKey);
        expect($secret->value())->toBe($longValue);
        expect(strlen($secret->key()))->toBe(900);
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
            key: 'REV_TEST',
            value: 'test',
            revision: 0
        );
        
        $secret2 = new Secret(
            key: 'REV_TEST',
            value: 'test',
            revision: 999999
        );
        
        $secret3 = new Secret(
            key: 'REV_TEST',
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
});
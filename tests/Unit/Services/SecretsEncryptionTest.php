<?php

use STS\Keep\Services\SecretsEncryption;

it('derives consistent key from app key', function () {
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    
    $key1 = SecretsEncryption::deriveKey($appKey);
    $key2 = SecretsEncryption::deriveKey($appKey);
    
    expect($key1)->toBe($key2);
    expect(strlen($key1))->toBe(64); // SHA-256 hex string
});

it('derives different keys for different app keys', function () {
    $appKey1 = 'base64:' . base64_encode(random_bytes(32));
    $appKey2 = 'base64:' . base64_encode(random_bytes(32));
    
    $key1 = SecretsEncryption::deriveKey($appKey1);
    $key2 = SecretsEncryption::deriveKey($appKey2);
    
    expect($key1)->not->toBe($key2);
});

it('can encrypt and decrypt secrets', function () {
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    $secrets = [
        'DB_PASSWORD' => 'secret-password',
        'API_KEY' => 'api-key-value',
        'EMPTY_VALUE' => '',
        'SPECIAL_CHARS' => 'special!@#$%^&*()chars',
    ];
    
    $encrypted = SecretsEncryption::encrypt($secrets, $appKey);
    expect($encrypted)->toBeString();
    expect(strlen($encrypted))->toBeGreaterThan(0);
    
    $decrypted = SecretsEncryption::decrypt($encrypted, $appKey);
    expect($decrypted)->toBe($secrets);
});

it('fails to decrypt with wrong app key', function () {
    $appKey1 = 'base64:' . base64_encode(random_bytes(32));
    $appKey2 = 'base64:' . base64_encode(random_bytes(32));
    $secrets = ['DB_PASSWORD' => 'secret-password'];
    
    $encrypted = SecretsEncryption::encrypt($secrets, $appKey1);
    
    expect(fn() => SecretsEncryption::decrypt($encrypted, $appKey2))
        ->toThrow(RuntimeException::class, 'Failed to decrypt secrets cache');
});

it('handles empty secrets array', function () {
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    $secrets = [];
    
    $encrypted = SecretsEncryption::encrypt($secrets, $appKey);
    $decrypted = SecretsEncryption::decrypt($encrypted, $appKey);
    
    expect($decrypted)->toBe([]);
});

it('produces different encrypted output each time', function () {
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    $secrets = ['DB_PASSWORD' => 'secret-password'];
    
    $encrypted1 = SecretsEncryption::encrypt($secrets, $appKey);
    $encrypted2 = SecretsEncryption::encrypt($secrets, $appKey);
    
    // Should be different due to random IV
    expect($encrypted1)->not->toBe($encrypted2);
    
    // But both should decrypt to same data
    $decrypted1 = SecretsEncryption::decrypt($encrypted1, $appKey);
    $decrypted2 = SecretsEncryption::decrypt($encrypted2, $appKey);
    
    expect($decrypted1)->toBe($secrets);
    expect($decrypted2)->toBe($secrets);
});
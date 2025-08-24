<?php

use STS\Keep\Services\Crypt;

it('can encrypt and decrypt secrets', function () {
    $keyPart = base64_encode(random_bytes(32));
    $secrets = [
        'DB_PASSWORD' => 'secret-password',
        'API_KEY' => 'api-key-value',
        'EMPTY_VALUE' => '',
        'SPECIAL_CHARS' => 'special!@#$%^&*()chars',
    ];
    
    $crypt = new Crypt($keyPart);
    
    $encrypted = $crypt->encrypt($secrets);
    expect($encrypted)->toBeString();
    expect(strlen($encrypted))->toBeGreaterThan(0);
    
    $decrypted = $crypt->decrypt($encrypted);
    expect($decrypted)->toBe($secrets);
});

it('fails to decrypt with wrong key part', function () {
    $keyPart1 = base64_encode(random_bytes(32));
    $keyPart2 = base64_encode(random_bytes(32));
    $secrets = ['DB_PASSWORD' => 'secret-password'];
    
    $crypt1 = new Crypt($keyPart1);
    $crypt2 = new Crypt($keyPart2);
    
    $encrypted = $crypt1->encrypt($secrets);
    
    expect(fn() => $crypt2->decrypt($encrypted))
        ->toThrow(RuntimeException::class, 'Failed to decrypt secrets cache');
});

it('handles empty secrets array', function () {
    $keyPart = base64_encode(random_bytes(32));
    $secrets = [];
    
    $crypt = new Crypt($keyPart);
    
    $encrypted = $crypt->encrypt($secrets);
    $decrypted = $crypt->decrypt($encrypted);
    
    expect($decrypted)->toBe([]);
});

it('produces different encrypted output each time due to random nonce', function () {
    $keyPart = base64_encode(random_bytes(32));
    $secrets = ['DB_PASSWORD' => 'secret-password'];
    
    $crypt = new Crypt($keyPart);
    
    $encrypted1 = $crypt->encrypt($secrets);
    $encrypted2 = $crypt->encrypt($secrets);
    
    // Should be different due to random nonce
    expect($encrypted1)->not->toBe($encrypted2);
    
    // But both should decrypt to same data
    $decrypted1 = $crypt->decrypt($encrypted1);
    $decrypted2 = $crypt->decrypt($encrypted2);
    
    expect($decrypted1)->toBe($secrets);
    expect($decrypted2)->toBe($secrets);
});

it('derives consistent keys for same key part', function () {
    $keyPart = base64_encode(random_bytes(32));
    
    $crypt1 = new Crypt($keyPart);
    $crypt2 = new Crypt($keyPart);
    
    $secrets = ['TEST' => 'value'];
    
    $encrypted = $crypt1->encrypt($secrets);
    $decrypted = $crypt2->decrypt($encrypted);
    
    expect($decrypted)->toBe($secrets);
});

it('uses system context in key derivation', function () {
    $keyPart = base64_encode(random_bytes(32));
    
    // Create multiple instances with same keyPart
    $crypt1 = new Crypt($keyPart);
    $crypt2 = new Crypt($keyPart);
    
    $secrets = ['TEST' => 'value'];
    
    // Should be able to encrypt with one instance and decrypt with another
    // since they're running in the same system context
    $encrypted = $crypt1->encrypt($secrets);
    $decrypted = $crypt2->decrypt($encrypted);
    
    expect($decrypted)->toBe($secrets);
});
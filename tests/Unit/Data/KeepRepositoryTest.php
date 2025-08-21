<?php

use STS\Keep\Data\KeepRepository;
use STS\Keep\Services\SecretsEncryption;

it('can get secrets from encrypted cache file', function () {
    $tempDir = createTempKeepDir();
    $cacheFile = $tempDir . '/cache.php';
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    
    // Create test secrets
    $secrets = [
        'DB_PASSWORD' => 'secret-password',
        'API_KEY' => 'api-key-value',
    ];
    
    // Create encrypted cache file
    $encryptedData = SecretsEncryption::encrypt($secrets, $appKey);
    $phpContent = "<?php\n\nreturn " . var_export($encryptedData, true) . ";\n";
    file_put_contents($cacheFile, $phpContent);
    
    // Test repository
    $repository = new KeepRepository($cacheFile, $appKey);
    
    expect($repository->get('DB_PASSWORD'))->toBe('secret-password');
    expect($repository->get('API_KEY'))->toBe('api-key-value');
    expect($repository->get('NON_EXISTENT'))->toBeNull();
    expect($repository->get('NON_EXISTENT', 'default'))->toBe('default');
    
    cleanupTempDir($tempDir);
});

it('can check if secrets exist', function () {
    $tempDir = createTempKeepDir();
    $cacheFile = $tempDir . '/cache.php';
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    
    // Create test secrets
    $secrets = [
        'DB_PASSWORD' => 'secret-password',
        'API_KEY' => 'api-key-value',
    ];
    
    // Create encrypted cache file
    $encryptedData = SecretsEncryption::encrypt($secrets, $appKey);
    $phpContent = "<?php\n\nreturn " . var_export($encryptedData, true) . ";\n";
    file_put_contents($cacheFile, $phpContent);
    
    // Test repository
    $repository = new KeepRepository($cacheFile, $appKey);
    
    expect($repository->has('DB_PASSWORD'))->toBeTrue();
    expect($repository->has('API_KEY'))->toBeTrue();
    expect($repository->has('NON_EXISTENT'))->toBeFalse();
    
    cleanupTempDir($tempDir);
});

it('can get all secrets', function () {
    $tempDir = createTempKeepDir();
    $cacheFile = $tempDir . '/cache.php';
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    
    // Create test secrets
    $secrets = [
        'DB_PASSWORD' => 'secret-password',
        'API_KEY' => 'api-key-value',
    ];
    
    // Create encrypted cache file
    $encryptedData = SecretsEncryption::encrypt($secrets, $appKey);
    $phpContent = "<?php\n\nreturn " . var_export($encryptedData, true) . ";\n";
    file_put_contents($cacheFile, $phpContent);
    
    // Test repository
    $repository = new KeepRepository($cacheFile, $appKey);
    
    expect($repository->all())->toBe($secrets);
    
    cleanupTempDir($tempDir);
});

it('returns empty array when cache file does not exist', function () {
    $tempDir = createTempKeepDir();
    $cacheFile = $tempDir . '/non-existent.php';
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    
    $repository = new KeepRepository($cacheFile, $appKey);
    
    expect($repository->get('ANY_KEY'))->toBeNull();
    expect($repository->has('ANY_KEY'))->toBeFalse();
    expect($repository->all())->toBe([]);
    
    cleanupTempDir($tempDir);
});

it('throws exception with invalid APP_KEY', function () {
    $tempDir = createTempKeepDir();
    $cacheFile = $tempDir . '/cache.php';
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    $wrongKey = 'base64:' . base64_encode(random_bytes(32));
    
    // Create test secrets with correct key
    $secrets = ['DB_PASSWORD' => 'secret-password'];
    $encryptedData = SecretsEncryption::encrypt($secrets, $appKey);
    $phpContent = "<?php\n\nreturn " . var_export($encryptedData, true) . ";\n";
    file_put_contents($cacheFile, $phpContent);
    
    // Constructor will try to decrypt with wrong key and should throw
    expect(fn() => new KeepRepository($cacheFile, $wrongKey))
        ->toThrow(RuntimeException::class, 'Failed to load secrets from cache file');
    
    cleanupTempDir($tempDir);
});

it('loads secrets at construction time', function () {
    $tempDir = createTempKeepDir();
    $cacheFile = $tempDir . '/cache.php';
    $appKey = 'base64:' . base64_encode(random_bytes(32));
    
    // Create test secrets
    $secrets = ['DB_PASSWORD' => 'secret-password'];
    $encryptedData = SecretsEncryption::encrypt($secrets, $appKey);
    $phpContent = "<?php\n\nreturn " . var_export($encryptedData, true) . ";\n";
    file_put_contents($cacheFile, $phpContent);
    
    // Create repository (should load secrets immediately)
    $repository = new KeepRepository($cacheFile, $appKey);
    
    // Delete the cache file to prove it was loaded at construction
    unlink($cacheFile);
    
    // Access should still work (loaded in memory during construction)
    expect($repository->get('DB_PASSWORD'))->toBe('secret-password');
    expect($repository->has('DB_PASSWORD'))->toBeTrue();
    expect($repository->all())->toBe($secrets);
    
    cleanupTempDir($tempDir);
});
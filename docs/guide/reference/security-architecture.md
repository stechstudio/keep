<!-- Future Enhancement: Encrypted Cache Architecture

# Encrypted Cache Architecture

Technical deep-dive into Keep's approach to caching secrets without ever exposing plaintext or decryption keys on disk.

## Design Philosophy

Keep's security model addresses a fundamental challenge in secret management: balancing security with performance. Our solution provides:

1. **Defense in depth** - Multiple layers of protection
2. **Zero-trust disk storage** - Never store plaintext secrets
3. **Performance optimization** - Eliminate repeated API calls
4. **Shared hosting compatibility** - Works without system-level access

## Threat Model

Keep protects against several attack vectors:

### File Traversal Attacks
**Threat**: Buggy application code that accidentally exposes file contents (e.g., path traversal vulnerability in a web endpoint).

**Mitigation**: Even if an attacker reads the encrypted cache file, they cannot decrypt it without:
- The partial key from `.env` 
- System context (hostname, working directory)
- Deployment fingerprint (composer packages)
- Process context (user)

### Backup/Log Exposure
**Threat**: Backup systems, logging, or monitoring tools capturing sensitive files.

**Mitigation**: All cached secrets remain encrypted. Backups only contain ciphertext.

### Memory Dumps
**Threat**: Memory dumps or swap files containing decrypted secrets.

**Mitigation**: Secrets are decrypted only when accessed and PHP's memory is cleared after each request in typical web scenarios.

## Encryption Implementation

### Algorithm Selection

Keep uses **Sodium's `crypto_secretbox`** (ChaCha20-Poly1305):

```php
// Encryption
$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
$encrypted = sodium_crypto_secretbox($plaintext, $nonce, $key);

// Decryption  
$plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
```

**Why ChaCha20-Poly1305?**
- **Authenticated encryption** - Detects tampering
- **Constant-time operations** - Immune to timing attacks
- **Performance** - Faster than AES on systems without hardware acceleration
- **Nonce-misuse resistance** - Each encryption uses a unique nonce

### Key Derivation

The encryption key is derived from multiple entropy sources:

```php
class Crypt {
    private function deriveEncryptionKey(string $keyPart): string {
        $sources = [
            gethostname(),                    // System identifier
            getcwd(),                         // Deployment path
            $this->getDeploymentHash(),       // Composer packages fingerprint
            get_current_user(),               // Process owner
            $keyPart,                         // Random component from .env
        ];

        $masterKey = hash('sha256', implode('|', $sources), true);
        
        return sodium_crypto_kdf_derive_from_key(
            32,                               // Key length
            1,                                // Subkey ID
            'KeepSecr',                       // Context (8 bytes)
            $masterKey
        );
    }
}
```

### Multi-Source Entropy

Each entropy source serves a specific purpose:

1. **`$keyPart` (32 bytes, base64)** 
   - Random value generated during cache creation
   - Stored in `.env` as `KEEP_CACHE_KEY_PART`
   - Provides cryptographic randomness

2. **`gethostname()`**
   - Ties cache to specific system
   - Prevents cache portability between servers
   - Changes if attacker moves cache file

3. **`getcwd()`**
   - Deployment path specificity
   - Protects against cache reuse in different directories
   - Common in shared hosting scenarios

4. **Deployment Hash**
   - SHA-256 of `vendor/composer/installed.php`
   - Changes with any package update
   - Cached by OpCache for performance

5. **`get_current_user()`**
   - Process owner context
   - Additional entropy in multi-user systems

## Cache File Structure

The encrypted cache is stored as a PHP file for OpCache optimization:

```php
<?php
// .keep/cache/production.keep.php
return 'base64_encoded_nonce_and_ciphertext';
```

**Benefits:**
- PHP OpCache caches the file in memory
- Faster than JSON/plain text files
- Automatic memory management

## Runtime Integration

### Accessing Secrets with `keep()`

The `keep()` helper function provides instant access to decrypted secrets from memory:

```php
// Secrets are decrypted once at runtime startup
$apiKey = keep('STRIPE_API_KEY');
$dbPassword = keep('DB_PASSWORD');

// Subsequent calls are simple array lookups (< 0.1ms)
$sameKey = keep('STRIPE_API_KEY');  // No decryption, just memory access
```

The entire cache is decrypted once when the application starts, then held in memory. This means:
- **Zero filesystem access** after initial load
- **No repeated decryption** - secrets are decrypted once per request
- **Sub-millisecond access** - simple array lookups from memory

### Performance Optimization

Key performance characteristics:

| Operation | Time | Notes |
|-----------|------|-------|
| Initial cache generation | 100-500ms | AWS API calls |
| Cache file loading | <1ms | OpCache optimized |
| Key derivation | ~1ms | One-time per request |
| Decryption | <1ms | ChaCha20 is fast |
| Secret access | <0.1ms | Array lookup |


## Security Boundaries

### What Keep Protects Against

✅ **File system attacks** - Encrypted cache prevents plaintext exposure  
✅ **Cross-deployment attacks** - Deployment-specific keys  
✅ **Tampering** - Authenticated encryption detects modifications  
✅ **Timing attacks** - Constant-time crypto operations  

### What Keep Does NOT Protect Against

❌ **Compromised application code** - If your app is compromised, secrets are accessible  
❌ **Memory access** - Decrypted secrets exist in memory during execution  
❌ **AWS credential theft** - Original vault access still requires AWS credentials  
❌ **Social engineering** - Human factors outside technical scope

## Real-World Attack Scenarios

### Scenario 1: WordPress Plugin Vulnerability
A vulnerable WordPress plugin allows arbitrary file reads.

**Without Keep:**
- Attacker reads `.env` → Gets database password → Dumps entire database

**With Keep:**
- Attacker reads encrypted cache → Cannot decrypt → No database access

### Scenario 2: S3 Bucket Misconfiguration
Your backup bucket is accidentally made public.

**Without Keep:**
- Anyone can download backups → Extract `.env` → Access all services

**With Keep:**
- Backups contain only encrypted blobs → Useless without runtime environment

### Scenario 3: GitHub Accident
Developer commits entire project to public repo.

**Without Keep:**
- Must rotate ALL secrets immediately
- Secrets forever in Git history

**With Keep:**
- Only partial key exposed
- Other key components not in Git
- No immediate threat

## Implementation Details

### File Permissions

Cache files are created with restrictive permissions:

```php
$this->filesystem->put($path, $content);
$this->filesystem->chmod($path, 0600);  // Read/write for owner only
```

### Error Handling

Decryption failures are handled gracefully:

```php
try {
    $decrypted = sodium_crypto_secretbox_open($encrypted, $nonce, $key);
    if ($decrypted === false) {
        throw new RuntimeException('Failed to decrypt secrets cache');
    }
} catch (Exception $e) {
    // Fallback to empty secrets or re-fetch from vault
    return [];
}
```

### Rotation Strategy

Regular key rotation is recommended:

1. **Automated rotation** - CI/CD regenerates cache on each deployment
2. **Time-based rotation** - Cron job refreshes cache periodically
3. **Event-based rotation** - Regenerate after security events

## Best Practices

### Development

1. Use local vault driver for development
2. Generate fresh cache for each environment
3. Never commit cache files or `KEEP_CACHE_KEY_PART`

### Production

1. Generate cache during deployment, not runtime
2. Monitor cache file permissions (should be 0600)
3. Set up alerts for decryption failures
4. Implement cache refresh strategy

### Security Hardening

1. **Restrict file access**: 
   ```bash
   chmod 600 .keep/cache/*.php
   ```

2. **Separate cache directory**:
   ```php
   'cache_path' => '/secure/location/keep.php',
   ```

3. **Additional entropy**:
   ```php
   // Add custom entropy source
   $sources[] = $_SERVER['CUSTOM_DEPLOYMENT_ID'] ?? '';
   ```

## Future Enhancements

Potential improvements under consideration:

1. **Hardware Security Module (HSM) integration** - For key management
2. **Asymmetric encryption option** - Public key encryption for cache
3. **Distributed caching** - Redis/Memcached backend support
4. **Audit logging** - Track all secret access
5. **Secret rotation webhooks** - Automatic cache refresh on vault changes

## Conclusion

Keep's security architecture provides a pragmatic balance between security and performance. By using defense-in-depth with multiple entropy sources and authenticated encryption, we significantly raise the bar for attackers while maintaining the sub-millisecond access times modern applications require.

The key insight is that perfect security is impossible once secrets are in memory, but we can make disk-based attacks orders of magnitude harder while keeping the developer experience simple and the performance overhead negligible.
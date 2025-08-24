# Runtime Secrets

Keep provides a secure, high-performance alternative to plaintext `.env` files by caching encrypted secrets that are decrypted only at runtime. This approach eliminates plaintext secrets on disk while maintaining the low latency your application needs.

## Why Runtime Secrets?

Traditional `.env` files expose your secrets as plaintext on disk, creating security vulnerabilities:
- File traversal attacks could expose all secrets
- Backup systems might capture plaintext secrets
- Developers might accidentally commit `.env` files

Keep's runtime secrets solution provides:
- **Zero plaintext exposure** - Secrets remain encrypted on disk
- **Low latency** - No repeated AWS API calls after initial cache
- **Seamless integration** - Works with Laravel's existing `env()` helper

## Caching Secrets

Generate an encrypted cache of your secrets:

```bash
keep cache --stage production
```

This command:
1. Fetches secrets from your configured vaults
2. Generates a unique encryption key
3. Encrypts secrets using Sodium (ChaCha20-Poly1305)
4. Saves encrypted cache to `.keep/cache/[stage].keep.php`
5. Adds the partial key component to your `.env` file as `KEEP_CACHE_KEY_PART`

### Cache Options

```bash
# Cache specific vaults only
keep cache --stage production --vault aws-ssm,secrets-manager
```

## Laravel Integration

Keep provides two integration methods for Laravel applications:

### Method 1: Seamless `env()` Integration

Keep registers a custom loader for the `env()` helper. To use this method, configure:

```bash
KEEP_INTEGRATION_MODE=env
```

Now use secrets normally in your config files:
```php
// config/database.php
'connections' => [
    'mysql' => [
        'host' => env('DB_HOST'),
        'username' => env('DB_USERNAME'),
        'password' => env('DB_PASSWORD'),
```

**⚠️ Important:** If you use `php artisan config:cache`, this method will write plaintext to the cached config file. For maximum security, use Method 2 instead.

### Method 2: Diskless `keep()` Helper

For zero plaintext exposure, configure Keep integration to use helper mode. Then use the dedicated helper in our application code, and not do _not_ use it in any config files that might be cached.

```bash
KEEP_INTEGRATION_MODE=helper
```

Access secrets using:
```php
$apiKey = keep('STRIPE_KEY');
```

This method ensures secrets are **never** written to disk in plaintext, even with config caching.

## Security Architecture

Keep's runtime secrets use multiple layers of protection:

### 1. Encryption
- **Algorithm**: Sodium's ChaCha20-Poly1305 (authenticated encryption)
- **Key Size**: 256-bit derived keys
- **Nonce**: Random per encryption

### 2. Key Derivation
The decryption key is derived from multiple sources at runtime:
- Random component stored in `.env` (`KEEP_CACHE_KEY_PART`)
- System context (hostname, path, user)
- Deployment fingerprint (composer packages hash)

This means:
- File traversal attacks cannot decrypt the cache (missing system context)
- The cache file alone is useless without the runtime environment
- Each deployment has unique encryption

### 3. File Permissions
Cache files are created with restricted permissions (0600) by default.

## CI/CD Integration

For automated deployments:

```yaml
# GitHub Actions example
- name: Cache secrets
  run: |
    keep cache --stage ${{ github.ref == 'refs/heads/main' && 'production' || 'staging' }}
    
- name: Deploy application
  run: |
    # Your deployment steps
    # .keep/cache/ directory contains encrypted secrets
    # .env contains KEEP_CACHE_KEY_PART
```

## Performance Considerations

- **Initial cache**: ~100-500ms to fetch from AWS
- **Runtime access**: <1ms (PHP OpCache optimized)
- **Memory usage**: Minimal (secrets loaded once per request)

## Troubleshooting

### "Failed to decrypt secrets cache"

This error indicates a key mismatch. Common causes:
- Missing `KEEP_CACHE_KEY_PART` in `.env`
- Cache file generated on different system
- Deployment path changed

**Solution**: Regenerate the cache with `keep cache`

### Secrets not available in Laravel

Ensure the service provider is registered:
```php
// config/app.php
'providers' => [
    // ...
    STS\Keep\Laravel\SecretsServiceProvider::class,
],
```

### Config caching concerns

If using `config:cache` with the `dotenv` mode, consider switching to `helper` mode:
```bash
# Update config/keep.php
'mode' => 'helper',

# Update your config files to use keep() instead of env()
'database.password' => keep('DB_PASSWORD'),
```

## Best Practices

1. **Generate cache during deployment** - Not in production runtime
2. **Exclude cache from version control** - Add `.keep/cache/` to `.gitignore`
3. **Use helper mode for maximum security** - Prevents any plaintext exposure
4. **Rotate encryption keys periodically** - Regenerate cache regularly
5. **Monitor cache file permissions** - Ensure 0600 or more restrictive

## Next Steps

- Learn about [templating](../templates.md) for complex deployments
- Explore [vault configuration](../vaults.md) options
- Read the [Security Architecture Reference](../../reference/security-architecture.md) for technical details
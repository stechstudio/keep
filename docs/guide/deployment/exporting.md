# Exporting to Files

Generate configuration files when file-based configuration is required. Consider [runtime injection](./runtime-injection.md) for production use.

## Basic Usage

```bash
# Export to .env file
keep export --stage=production --file=.env

# Different formats
keep export --stage=production --format=json --file=config.json
keep export --stage=production --format=csv --file=secrets.csv

# Export to stdout for piping
keep export --stage=production | grep API_KEY
```

## Filtering Exports

Export only the secrets you need:

### Include Specific Keys
```bash
# Export only API-related secrets
keep export --stage=production --only="API_*,TOKEN_*" --file=api.env

# Export specific keys
keep export --stage=production --only="DATABASE_URL,REDIS_URL" --file=db.env
```

### Exclude Sensitive Keys
```bash
# Export all except private keys
keep export --stage=production --except="*_PRIVATE_KEY,*_SECRET" --file=.env

# Multiple exclusion patterns
keep export --stage=production --except="DEBUG_*,TEST_*" --file=prod.env
```

## File Operations

### Overwrite Protection
By default, Keep prompts before overwriting:
```bash
# Prompt before overwrite (default)
keep export --stage=production --file=.env

# Force overwrite without prompt
keep export --stage=production --file=.env --overwrite
```

### Append Mode
Add secrets to existing files:
```bash
# Append to existing .env file
keep export --stage=production --file=.env --append

# Useful for combining multiple vaults
keep export --vault=ssm --stage=prod --file=.env
keep export --vault=secretsmanager --stage=prod --file=.env --append
```

## Template-Based Export

Use templates for complete configuration with both secrets and static values:

```bash
# Export using template
keep export --template=env/production.env --stage=production --file=.env

# Include all secrets beyond template
keep export --template=env/production.env --stage=production --all --file=.env
```

See [Managing Templates](./templates.md) for creating and managing templates.

## Common Use Cases

```bash
# Local development
keep export --stage=local --file=.env --overwrite

# Docker deployment
keep export --stage=production --file=docker.env
docker run --env-file=docker.env myapp:latest

# Backup secrets
keep export --stage=production --format=json --file=backup-$(date +%Y%m%d).json

# Preview without writing
keep export --stage=production --only="DB_*"
```

## Security Notes

⚠️ **Exported files contain plaintext secrets**

```bash
# Set restrictive permissions
chmod 600 .env

# Delete after use
rm -f .env docker.env

# Never commit to version control
echo "*.env" >> .gitignore
```

For production, use [runtime injection](./runtime-injection.md) instead - it never writes secrets to disk.
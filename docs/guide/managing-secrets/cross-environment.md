# Cross-Environment Operations

## Copying Secrets

`keep copy` copies secrets between stages or vaults, supporting both single secret and bulk operations.

### Command Reference: `keep copy`

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--from` | string | *required* | Source context (stage or vault:stage) |
| `--to` | string | *required* | Destination context (stage or vault:stage) |
| `--overwrite` | boolean | `false` | Overwrite existing secrets without confirmation |
| `--dry-run` | boolean | `false` | Show what would be copied without making changes |
| `--only` | string | | Pattern for bulk copy - include only matching keys |
| `--except` | string | | Pattern for bulk copy - exclude matching keys |

**Arguments:**
- `[key]` - Specific secret key to copy (omit when using --only or --except)

### Single Secret Copy

```bash
# Basic copy
keep copy DB_PASSWORD --from=local --to=staging

# With overwrite
keep copy DB_PASSWORD --from=local --to=staging --overwrite

# Dry run first
keep copy API_KEY --from=staging --to=production --dry-run

# Cross-vault
keep copy DB_PASSWORD --from=secretsmanager:local --to=ssm:production
```

### Bulk Copy Operations

Copy multiple secrets at once using pattern matching:

```bash
# Copy all database configurations
keep copy --only="DB_*" --from=local --to=staging

# Copy everything except sensitive keys
keep copy --except="*_SECRET,*_TOKEN" --from=staging --to=production

# Copy all API-related secrets with overwrite
keep copy --only="API_*" --from=local --to=production --overwrite

# Preview what would be copied (dry-run)
keep copy --only="*" --from=staging --to=production --dry-run

# Complex patterns - copy configs but not passwords
keep copy --only="*_CONFIG,*_URL" --except="*_PASSWORD" --from=dev --to=staging
```

## Comparing Environments

`keep diff` shows differences between stages and vaults.

### Command Reference: `keep diff`

| Option     | Type | Default      | Description                                       |
|------------|------|--------------|---------------------------------------------------|
| `--vault`  | string | *all vaults* | Comma-separate list of vaults to compare          |
| `--stage`  | string | *all stages* | Comma-separated list of stages to compare |
| `--unmask` | boolean | `false`      | Show actual secret values (not masked)            |
| `--only`   | string |              | Comma-separated list of keys to include           |
| `--except` | string |              | Comma-separated list of keys to exclude           |

**Examples:**
```bash
# Compare all
keep diff

# Specific stages
keep diff --stage=staging,production

# Show values
keep diff --stage=staging,production --unmask

# Filter keys
keep diff --stage=local,production --only="DB_*"
keep diff --stage=local,production --except="APP_DEBUG"
```

## Importing Secrets

`keep import` imports secrets from `.env` files.

### Command Reference: `keep import`

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--stage` | string | *interactive* | Target stage to import secrets into |
| `--vault` | string | *default vault* | Vault to import secrets into |
| `--skip-existing` | boolean | `false` | Skip secrets that already exist |
| `--overwrite` | boolean | `false` | Overwrite existing secrets without confirmation |
| `--dry-run` | boolean | `false` | Show what would be imported without making changes |
| `--only` | string | | Comma-separated list of keys to import |
| `--except` | string | | Comma-separated list of keys to exclude |

**Arguments:**
- `[file]` - Path to file to import from (uses stdin if not provided)

**Examples:**
```bash
# Import from .env file
keep import .env.development --stage=local

# Import with existing secret protection
keep import production.env --stage=production --skip-existing

# Force overwrite existing secrets
keep import staging.env --stage=staging --overwrite

# Dry run to preview import
keep import .env --stage=local --dry-run

# Import only specific keys
keep import secrets.json --stage=production --only="API_KEY,DB_PASSWORD"

# Import from stdin
cat .env | keep import --stage=local

# Exclude sensitive keys
keep import .env --stage=local --except="PRIVATE_KEY"
```

## Promotion Workflows

### Individual Secret Promotion

```bash
# 1. Review current state
keep diff --stage=local,staging

# 2. Copy individual secrets as needed
keep copy API_KEY --from=local --to=staging
keep copy DB_USERNAME --from=local --to=staging

# 3. Set staging-specific values
keep set API_URL "https://staging-api.example.com" --stage=staging

# 4. Verify the promotion
keep diff --stage=local,staging
```

### Bulk Promotion

```bash
# 1. Preview what will be promoted
keep copy --only="*" --from=local --to=staging --dry-run

# 2. Promote all configs except debug/test values
keep copy --except="*_DEBUG,*_TEST" --from=local --to=staging

# 3. Or promote specific service configurations
keep copy --only="API_*,DB_*,REDIS_*" --from=local --to=staging

# 4. Verify the promotion
keep diff --stage=local,staging
```

### Cross-Vault Promotion

```bash
# Individual secrets
keep copy API_KEY --from=secretsmanager:local --to=ssm:production --dry-run
keep copy API_KEY --from=secretsmanager:local --to=ssm:production
keep copy DB_PASSWORD --from=secretsmanager:local --to=ssm:production

# Bulk cross-vault promotion
keep copy --only="API_*" --from=secretsmanager:local --to=ssm:production
keep copy --except="*_LOCAL" --from=secretsmanager:staging --to=ssm:production
```

## Best Practices

**Always dry-run first:**
```bash
keep copy API_KEY --from=staging --to=production --dry-run
keep import prod-secrets.env --stage=production --dry-run
```

**Review before promoting:**
```bash
keep diff --stage=staging,production --unmask
```

**Keep environments isolated:**
```bash
# Set environment-specific values explicitly
keep set DB_HOST "staging-db.example.com" --stage=staging
keep set DB_HOST "prod-db.example.com" --stage=production
```

**Safe imports:**
```bash
keep import backup.env --stage=production --skip-existing
```

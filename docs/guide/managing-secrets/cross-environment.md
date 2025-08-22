# Cross-Environment Operations

This guide covers operations that work across stages and vaults: copying secrets, importing from files, and promoting secrets through your deployment pipeline.

## Copying Secrets

The `keep copy` command copies secrets between stages or vaults, enabling promotion workflows and environment synchronization.

### Basic Usage

```bash
# Copy specific secret between stages
keep copy DB_PASSWORD --from=development --to=staging

# Copy between different vaults
keep copy API_KEY --from=secretsmanager:development --to=ssm:production
```

### Command Reference: `keep copy`

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--from` | string | *required* | Source context (stage or vault:stage) |
| `--to` | string | *required* | Destination context (stage or vault:stage) |
| `--overwrite` | boolean | `false` | Overwrite existing secrets without confirmation |
| `--dry-run` | boolean | `false` | Show what would be copied without making changes |

**Arguments:**
- `[key]` - Specific secret key to copy (required)

**Examples:**
```bash
# Copy specific secret
keep copy DB_PASSWORD --from=development --to=staging

# Copy with overwrite (no confirmation)
keep copy DB_PASSWORD --from=development --to=staging --overwrite

# Dry run to preview changes
keep copy API_KEY --from=staging --to=production --dry-run

# Cross-vault copy
keep copy DB_PASSWORD --from=secretsmanager:development --to=ssm:production
```

## Comparing Environments

The `keep diff` command shows differences between stages and vaults, helping you understand what secrets exist where.

By default, all configured vaults and stages are compared, but you can specify particular ones.

### Basic Usage

```bash
# Compare all configured stages
keep diff

# Compare specific stages
keep diff --stage=staging,production

# Specify vault and stages
keep diff --stage=development,production --vault=ssm
```

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
# Compare all configured vaults and stages
keep diff

# Compare staging and production
keep diff --stage=staging,production

# Show actual values for comparison
keep diff --stage=staging,production --unmask

# Compare specific key patterns
keep diff --stage=development,production --only="DB_*"

# Exclude specific keys from comparison
keep diff --stage=development,production --except="APP_DEBUG"
```

## Importing Secrets

The `keep import` command imports secrets from `.env` files

### Basic Usage

```bash
# Import from .env file
keep import .env --stage=development

# Import with overwrite protection
keep import production.env --stage=production --skip-existing

# Import and deliberately overwrite existing secrets
keep import production.env --stage=production --overwrite

# Import to specific vault
keep import secrets.json --stage=staging --vault=ssm
```

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
keep import .env.development --stage=development

# Import with existing secret protection
keep import production.env --stage=production --skip-existing

# Force overwrite existing secrets
keep import staging.env --stage=staging --overwrite

# Dry run to preview import
keep import .env --stage=development --dry-run

# Import only specific keys
keep import secrets.json --stage=production --only="API_KEY,DB_PASSWORD"

# Import from stdin
cat .env | keep import --stage=development

# Exclude sensitive keys
keep import .env --stage=development --except="PRIVATE_KEY"
```

## Promotion Workflows

```bash
# 1. Review current state
keep diff --stage=development,staging

# 2. Copy individual secrets as needed
keep copy API_KEY --from=development --to=staging
keep copy DB_USERNAME --from=development --to=staging

# 3. Set staging-specific values
keep set API_URL "https://staging-api.example.com" --stage=staging

# 4. Verify the promotion
keep diff --stage=development,staging --keys-only
```

### Cross-Vault Promotion

```bash
# Promote from local development to AWS production
keep copy API_KEY --from=secretsmanager:development --to=ssm:production --dry-run
keep copy API_KEY --from=secretsmanager:development --to=ssm:production
keep copy DB_PASSWORD --from=secretsmanager:development --to=ssm:production
```

## Best Practices

### Promotion Safety

```bash
# Always use dry-run first
keep copy API_KEY --from=staging --to=production --dry-run

# Review differences before promotion
keep diff --stage=staging,production --unmask

# Copy specific secrets individually for safer operations
keep copy API_KEY --from=staging --to=production
keep copy APP_VERSION --from=staging --to=production
```

### Environment Isolation

```bash
# Copy only appropriate secrets between environments
keep copy API_KEY --from=development --to=staging
keep copy APP_VERSION --from=development --to=staging

# Set environment-specific values explicitly
keep set DB_HOST "staging-db.example.com" --stage=staging
keep set DB_HOST "prod-db.example.com" --stage=production
```

### Import Validation

```bash
# Always validate imports with dry-run
keep import prod-secrets.env --stage=production --dry-run

# Use skip-existing for safe imports
keep import backup.env --stage=production --skip-existing

# Audit after import
keep list --stage=production --format=json > post-import-audit.json
```

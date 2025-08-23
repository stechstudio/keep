# Creating & Viewing Secrets

## Setting Secrets

`keep set` creates or updates secrets in your vaults.

### Command Reference: `keep set`

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--stage` | string | *interactive* | Target stage (development, staging, production) |
| `--vault` | string | *default vault* | Vault to store the secret in |
| `--secure` | boolean | `true` | Whether to encrypt the secret (always true for AWS vaults) |
| `--force` | boolean | `false` | Overwrite existing secrets without confirmation |

**Arguments:**
- `[key]` - Secret key name (prompted if not provided)
- `[value]` - Secret value (prompted if not provided)

**Examples:**
```bash
# Interactive mode
keep set

# Basic usage
keep set DB_PASSWORD "my-secret" --stage=development

# Force overwrite
keep set API_KEY "new-value" --stage=production --force

# Specific vault
keep set STRIPE_KEY "sk_live_..." --stage=production --vault=secretsmanager
```

## Getting Secrets

`keep get` retrieves a specific secret from a vault.

### Command Reference: `keep get`

| Option | Type | Default         | Description                                    |
|--------|------|-----------------|------------------------------------------------|
| `--stage` | string | *interactive*   | Source stage to retrieve from                  |
| `--vault` | string | *default vault* | Vault to retrieve the secret from              |
| `--format` | string | `table`         | Output format: `table`, `json`, `raw`          |

**Arguments:**
- `[key]` - Secret key name (prompted if not provided)

**Examples:**
```bash
# Interactive mode
keep get

# Basic retrieval
keep get API_KEY --stage=development

# JSON output
keep get STRIPE_KEY --stage=production --format=json

# Raw format from specific vault
keep get CONFIG_JSON --stage=staging --vault=ssm --format=raw
```

## Listing Secrets

`keep list` shows all secrets in a stage or vault.

### Command Reference: `keep list`

| Option | Type | Default | Description                                 |
|--------|------|---------|---------------------------------------------|
| `--stage` | string | *interactive* | Stage to list secrets from                  |
| `--vault` | string | *default vault* | Vault to list secrets from                  |
| `--unmask` | boolean | `false` | Show actual secret values instead of masked |
| `--format` | string | `table` | Output format: `table`, `json`, `env`       |
| `--only` | string | | Comma-separated list of keys to include     |
| `--except` | string | | Comma-separated list of keys to exclude     |

**Examples:**
```bash
# Basic listing
keep list --stage=development

# Show actual values
keep list --stage=production --unmask

# Filter keys
keep list --stage=production --only="API_*,MAIL_*"
keep list --stage=development --except="DB_*,STRIPE_*"

# Different formats
keep list --stage=staging --format=json
keep list --stage=production --vault=secretsmanager --format=env
```

## Deleting Secrets

`keep delete` removes secrets from vaults.

### Command Reference: `keep delete`

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--stage` | string | *interactive* | Stage to delete secret from |
| `--vault` | string | *default vault* | Vault to delete the secret from |
| `--force` | boolean | `false` | Delete without confirmation prompt |

**Arguments:**
- `[key]` - Secret key name (prompted if not provided)

**Examples:**
```bash
# Interactive mode
keep delete

# Basic deletion
keep delete OLD_CONFIG --stage=development

# Force deletion
keep delete TEMP_KEY --stage=staging --force

# Specific vault
keep delete LEGACY_SECRET --stage=production --vault=ssm
```

## Best Practices

### Naming Conventions
- Use `UPPER_CASE` with underscores
- Include purpose: `DB_PASSWORD`, `API_KEY`, `STRIPE_SECRET`
- Stick to letters, numbers, underscores, and hyphens

### Security
- Never log unmasked values
- Use `--unmask` sparingly
- Verify stage before production changes
- Be careful with `--force`

### Common Workflows

```bash
# Development workflow
keep set DB_PASSWORD "dev-password" --stage=development
keep export --stage=development --output=.env.local
keep list --stage=development

# Production workflow (be careful!)
keep list --stage=production  # Verify stage first
keep set DB_PASSWORD "prod-password" --stage=production
keep export --stage=production --output=.env.production
```
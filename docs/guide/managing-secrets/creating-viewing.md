# Creating & Viewing Secrets

This guide covers the fundamental operations for working with individual secrets: creating, retrieving, and listing them.

## Setting Secrets

The `keep set` command creates or updates secrets in your vaults.

### Basic Usage

```bash
# Interactive mode - prompts for key, value, and stage
keep set

# Direct mode - specify everything
keep set API_KEY "abc123" --stage=development

# With specific vault
keep set DB_PASSWORD "secret" --stage=production --vault=ssm
```

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
# Basic secret creation
keep set DB_PASSWORD "my-secret" --stage=development

# Force overwrite existing secret
keep set API_KEY "new-value" --stage=production --force

# Specify vault explicitly
keep set STRIPE_KEY "sk_live_..." --stage=production --vault=secretsmanager

# Interactive mode (prompts for all inputs)
keep set
```

## Getting Individual Secrets

The `keep get` command retrieves a specific secret from a vault.

### Basic Usage

```bash
# Interactive mode - prompts for key and stage
keep get

# Direct mode
keep get API_KEY --stage=development

# Show unmasked value
keep get DB_PASSWORD --stage=production --unmask
```

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
# Basic secret retrieval formatted as a table
keep get API_KEY --stage=development

# JSON output format
keep get STRIPE_KEY --stage=production --format=json

# From specific vault and raw format
keep get CONFIG_JSON --stage=staging --vault=ssm --format=raw

# Interactive mode
keep get
```

## Listing Secrets

The `keep list` command shows all secrets in a stage or vault.

### Basic Usage

```bash
# List all secrets in development (masked)
keep list --stage=development

# List with actual values
keep list --stage=production --unmask

# List from specific vault
keep list --stage=staging --vault=secretsmanager
```

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
# Basic listing (masked values)
keep list --stage=development

# Show actual values
keep list --stage=production --unmask

# Include only specific keys
keep list --stage=production --only="NIGHTWATCH_*,MAIL_*"

# Exclude certain key prefixes
keep list --stage=development --except="DB_*,STRIPE_*"

# JSON output format
keep list --stage=staging --format=json

# From specific vault in key/value env format
keep list --stage=production --vault=secretsmanager --format=env
```

## Deleting Secrets

The `keep delete` command removes secrets from vaults.

### Basic Usage

```bash
# Interactive mode
keep delete

# Direct mode
keep delete OLD_API_KEY --stage=development

# Force deletion without confirmation
keep delete TEMP_SECRET --stage=development --force
```

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
# Basic deletion (with confirmation)
keep delete OLD_CONFIG --stage=development

# Force deletion without prompt
keep delete TEMP_KEY --stage=staging --force

# Delete from specific vault
keep delete LEGACY_SECRET --stage=production --vault=ssm

# Interactive mode
keep delete
```

## Best Practices

### Secret Naming
- Use **UPPER_CASE** with underscores for consistency
- Include **purpose** in the name: `DB_PASSWORD`, `API_KEY`, `STRIPE_SECRET`
- Avoid **special characters** beyond underscores and hyphens
- Keep names **descriptive** but not too long

### Security Considerations
- **Never log** unmasked secret values
- Use `--unmask` **sparingly** and only when necessary
- **Verify stage** before setting production secrets
- **Use force carefully** - always verify before overwriting

### Development Workflow
```bash
# 1. Set development secrets
keep set DB_PASSWORD "dev-password" --stage=development

# 2. Test with local export
keep export --stage=development --output=.env.local

# 3. Verify secrets are working
keep list --stage=development --pattern="DB_*"

# 4. Promote to staging when ready
keep copy DB_PASSWORD --from=development --to=staging
```

### Production Workflow
```bash
# 1. Always verify target stage
keep list --stage=production

# 2. Set production values carefully
keep set DB_PASSWORD "prod-password" --stage=production

# 3. Verify the secret was set correctly
keep get DB_PASSWORD --stage=production --unmask

# 4. Test deployment configuration
keep export --stage=production --output=.env.production
```

## Common Patterns

### Interactive Secret Management
```bash
# For sensitive operations, use interactive mode
keep set     # Prompts hide sensitive input
keep get     # Select key/stage interactively
keep delete  # Confirmation prompts prevent mistakes
```

### Bulk Secret Verification
```bash
# Check all secrets in a stage
keep list --stage=production --unmask > secrets-audit.txt

# Verify specific patterns
keep list --stage=production --only="API_*" --unmask

# Export for deployment verification
keep export --stage=production --format=json --output=deployment-secrets.json
```
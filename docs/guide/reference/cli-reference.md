# CLI Reference

Complete reference for all Keep commands with their options and usage examples.

## `keep configure`

Configure Keep settings and vault connections.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--no-interaction` | boolean | `false` | Run without prompts using defaults |

**Examples:**
```bash
# Interactive configuration
keep configure

# Non-interactive configuration
keep configure --no-interaction
```

## `keep vault:add`

Add a new vault configuration.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--driver` | string | *interactive* | Vault driver type |
| `--name` | string | *interactive* | Vault name |

**Examples:**
```bash
# Interactive vault addition
keep vault:add

# Specify driver and name
keep vault:add --driver=aws-ssm --name=production-ssm
```

## `keep vault:list`

List all configured vaults.

**Examples:**
```bash
# List all vaults
keep vault:list
```

## `keep set`

Create or update secrets in vaults.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--stage` | string | *interactive* | Target stage (development, staging, production) |
| `--vault` | string | *default vault* | Vault to store the secret in |
| `--secure` | boolean | `true` | Whether to encrypt the secret |
| `--force` | boolean | `false` | Overwrite existing secrets without confirmation |

**Arguments:**
- `[key]` - Secret key name (prompted if not provided)
- `[value]` - Secret value (prompted if not provided)

**Examples:**
```bash
# Interactive mode
keep set

# Direct mode
keep set API_KEY "abc123" --stage=development

# Force overwrite
keep set API_KEY "new-value" --stage=production --force

# Specify vault
keep set STRIPE_KEY "sk_live_..." --stage=production --vault=secretsmanager
```

## `keep get`

Retrieve a specific secret from a vault.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--stage` | string | *interactive* | Source stage to retrieve from |
| `--vault` | string | *default vault* | Vault to retrieve the secret from |
| `--format` | string | `table` | Output format: `table`, `json`, `raw` |

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

## `keep list`

Show all secrets in a stage or vault.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--stage` | string | *interactive* | Stage to list secrets from |
| `--vault` | string | *default vault* | Vault to list secrets from |
| `--unmask` | boolean | `false` | Show actual secret values instead of masked |
| `--format` | string | `table` | Output format: `table`, `json`, `env` |
| `--only` | string | | Comma-separated list of keys to include |
| `--except` | string | | Comma-separated list of keys to exclude |

**Examples:**
```bash
# Basic listing (masked values)
keep list --stage=development

# Show actual values
keep list --stage=production --unmask

# Include only specific keys
keep list --stage=production --only="NIGHTWATCH_*,MAIL_*"

# Exclude certain keys
keep list --stage=development --except="DB_*,STRIPE_*"

# JSON output
keep list --stage=staging --format=json

# From specific vault in env format
keep list --stage=production --vault=secretsmanager --format=env
```

## `keep delete`

Remove secrets from vaults.

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

# Basic deletion (with confirmation)
keep delete OLD_CONFIG --stage=development

# Force deletion without prompt
keep delete TEMP_KEY --stage=staging --force

# Delete from specific vault
keep delete LEGACY_SECRET --stage=production --vault=ssm
```

## `keep copy`

Copy secrets between stages or vaults.

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
# Copy between stages
keep copy DB_PASSWORD --from=development --to=staging

# Copy with overwrite
keep copy DB_PASSWORD --from=development --to=staging --overwrite

# Dry run
keep copy API_KEY --from=staging --to=production --dry-run

# Cross-vault copy
keep copy DB_PASSWORD --from=secretsmanager:development --to=ssm:production
```

## `keep diff`

Show differences between stages and vaults.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--vault` | string | *all vaults* | Comma-separated list of vaults to compare |
| `--stage` | string | *all stages* | Comma-separated list of stages to compare |
| `--unmask` | boolean | `false` | Show actual secret values (not masked) |
| `--only` | string | | Comma-separated list of keys to include |
| `--except` | string | | Comma-separated list of keys to exclude |

**Examples:**
```bash
# Compare all configured vaults and stages
keep diff

# Compare specific stages
keep diff --stage=staging,production

# Show actual values
keep diff --stage=staging,production --unmask

# Compare specific keys only
keep diff --stage=development,production --only="DB_*"

# Exclude specific keys
keep diff --stage=development,production --except="APP_DEBUG"
```

## `keep import`

Import secrets from `.env` files.

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

## `keep export`

Export secrets from vaults with optional template processing.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--stage` | string | *interactive* | Stage to export secrets from |
| `--vault` | string | *auto-discover* | Vault(s) to export from (comma-separated) |
| `--format` | string | `env` | Output format: `env`, `json` |
| `--template` | string | | Optional template file with placeholders |
| `--all` | boolean | `false` | With template: also append non-placeholder secrets |
| `--missing` | string | `fail` | Strategy for missing secrets: `fail`, `remove`, `blank`, `skip` |
| `--output` | string | *stdout* | Output file path |
| `--append` | boolean | `false` | Append to output file instead of overwriting |
| `--overwrite` | boolean | `false` | Overwrite output file without confirmation |
| `--only` | string | | Comma-separated list of keys to include |
| `--except` | string | | Comma-separated list of keys to exclude |

### Direct Export Mode (no template)

Export all secrets from specified vaults:

```bash
# Basic .env export
keep export --stage=production --output=.env

# JSON export
keep export --stage=production --format=json --output=config.json

# Export from specific vaults
keep export --stage=production --vault=ssm,secretsmanager --output=.env

# Export with filtering
keep export --stage=production --only="API_*,DB_*" --output=.env
```

### Template Mode (with template)

Use templates with placeholder syntax `{vault:key}`:

```bash
# Basic template merge (preserves structure)
keep export --stage=production --template=.env.template --output=.env

# Template with all additional secrets appended
keep export --stage=production --template=.env.template --all --output=.env

# Template to JSON (parses and transforms)
keep export --stage=production --template=.env.template --format=json --output=config.json

# Multiple templates can be combined using standard tools
cat .env.base .env.prod | keep export --template=/dev/stdin --stage=production --output=.env

# Handle missing secrets gracefully
keep export --stage=production --template=.env.template --missing=skip --output=.env
```

**Template Syntax:**
```bash
# Specify vault and secret name
API_KEY={ssm:service-api-key}

# If key name matches secret name, omit the secret name
DB_PASSWORD={ssm}

# Multiple vaults supported
REDIS_URL={secretsmanager:REDIS_URL}
```


## `keep cache`

Manage the secret cache.

**Examples:**
```bash
# Clear cache
keep cache:clear

# Show cache status
keep cache:status
```

## Getting Help

Each command includes detailed help:

```bash
keep --help
keep set --help
keep get --help
keep list --help
```

Use `--help` with any command to see its specific options and usage examples.
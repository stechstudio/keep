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

## `keep stage:add`

Add a custom stage/environment beyond the standard ones (local, staging, production).

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--no-interaction` | boolean | `false` | Run without prompts |

**Arguments:**
- `[name]` - Stage name (prompted if not provided)

**Examples:**
```bash
# Interactive mode
keep stage:add

# Direct mode with stage name
keep stage:add integration

# Add multiple custom stages
keep stage:add demo
keep stage:add qa
keep stage:add hotfix

# Non-interactive mode
keep stage:add sandbox --no-interaction
```

**Stage Name Requirements:**
- Must be lowercase
- Can contain letters, numbers, hyphens, and underscores
- Examples: `qa`, `demo`, `integration`, `sandbox`, `dev2`, `staging-eu`

## `keep set`

Create or update secrets in vaults.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--stage` | string | *interactive* | Target stage (local, staging, production) |
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
keep set API_KEY "abc123" --stage=local

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
keep get API_KEY --stage=local

# JSON output
keep get STRIPE_KEY --stage=production --format=json

# Raw format from specific vault
keep get CONFIG_JSON --stage=staging --vault=ssm --format=raw
```

## `keep show`

Show all secrets from a vault and stage.

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
keep show --stage=local

# Show actual values
keep show --stage=production --unmask

# Include only specific keys
keep show --stage=production --only="NIGHTWATCH_*,MAIL_*"

# Exclude certain keys
keep show --stage=local --except="DB_*,STRIPE_*"

# JSON output
keep show --stage=staging --format=json

# From specific vault in env format
keep show --stage=production --vault=secretsmanager --format=env
```

## `keep shell`

Start an interactive shell for Keep commands with persistent context.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--stage` | string | *first configured stage* | Initial stage to use |
| `--vault` | string | *default vault* | Initial vault to use |

### Shell Mode Features

The interactive shell provides:
- **Persistent context**: No need to specify --stage and --vault for each command
- **Command shortcuts**: Quick aliases for common commands
- **Context switching**: Easy switching between stages and vaults
- **Command history**: Access previous commands with arrow keys

### Shell Commands

**Context Management:**
```bash
keep> stage production    # Switch to production stage (alias: s)
keep> vault ssm          # Switch to ssm vault (alias: v)
keep> use ssm:production # Switch both at once (alias: u)
keep> context            # Show current context (alias: ctx)
```

**Secret Operations:**
```bash
keep> set API_KEY value  # Set a secret
keep> get API_KEY        # Get a secret (alias: g)
keep> delete API_KEY     # Delete a secret (alias: d)
keep> show               # List all secrets (aliases: ls, list, l)
keep> copy KEY --to=prod # Copy using current context as source
```

**Shell Control:**
```bash
keep> help               # Show available commands (alias: ?)
keep> history            # Show command history (alias: h)
keep> clear              # Clear screen (alias: cls)
keep> exit               # Exit shell (aliases: quit, q)
```

### Examples

```bash
# Start shell with initial context
keep shell --stage=production --vault=ssm

# Interactive session
keep (ssm:production)> show
keep (ssm:production)> stage development
✓ Switched to stage: development
keep (ssm:development)> set API_KEY "dev-key"
keep (ssm:development)> copy API_KEY --to=production
keep (ssm:development)> exit
Goodbye!
```

### Tips
- Use partial names for stages/vaults (e.g., `s prod` for `stage production`)
- All standard Keep commands work in the shell
- Commands automatically use the current context
- Use tab for basic command completion (if readline is available)

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
keep delete OLD_CONFIG --stage=local

# Force deletion without prompt
keep delete TEMP_KEY --stage=staging --force

# Delete from specific vault
keep delete LEGACY_SECRET --stage=production --vault=ssm
```

## `keep rename`

Rename a secret while preserving its value and metadata.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--stage` | string | *interactive* | Stage where the secret exists |
| `--vault` | string | *default vault* | Vault containing the secret |
| `--force` | boolean | `false` | Skip confirmation prompt |

**Arguments:**
- `old` - Current secret key name
- `new` - New secret key name

**Examples:**
```bash
# Rename with confirmation
keep rename DB_PASS DB_PASSWORD --stage=local

# Force rename without prompt
keep rename OLD_API_KEY NEW_API_KEY --stage=production --force

# Rename in specific vault
keep rename LEGACY_NAME MODERN_NAME --stage=staging --vault=ssm
```

**Note:** Neither AWS SSM nor Secrets Manager support native rename operations. This command performs a copy + delete operation, which is the AWS-recommended approach.

## `keep search`

Search for text within secret values.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--stage` | string | *interactive* | Stage to search in |
| `--vault` | string | *default vault* | Vault to search in |
| `--unmask` | boolean | `false` | Show actual secret values in results |
| `--case-sensitive` | boolean | `false` | Make the search case-sensitive |
| `--format` | string | `table` | Output format: `table` or `json` |
| `--only` | string | | Comma-separated list of keys to search within |
| `--except` | string | | Comma-separated list of keys to exclude from search |

**Arguments:**
- `query` - Text to search for in secret values

**Examples:**
```bash
# Basic search (values masked)
keep search "api.example.com" --stage=production

# Search with actual values shown
keep search "localhost" --stage=local --unmask

# Case-sensitive search
keep search "MySpecificValue" --stage=staging --case-sensitive

# Search only in specific keys
keep search "postgres" --stage=production --only="DB_*,DATABASE_*"

# JSON output
keep search "secret" --stage=local --format=json
```

**Search Results:**
- Matched text is highlighted with `>>>text<<<` markers when using `--unmask`
- Shows the key name, masked/unmasked value, and revision for each match
- Returns success even when no matches are found

## `keep copy`

Copy secrets between stages or vaults. Supports both single secret and bulk operations with pattern matching.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--from` | string | *required* | Source context (stage or vault:stage) |
| `--to` | string | *required* | Destination context (stage or vault:stage) |
| `--overwrite` | boolean | `false` | Overwrite existing secrets without confirmation |
| `--dry-run` | boolean | `false` | Show what would be copied without making changes |
| `--only` | string | | Pattern for bulk copy - include only matching keys (e.g., `DB_*`) |
| `--except` | string | | Pattern for bulk copy - exclude matching keys (e.g., `*_SECRET`) |

**Arguments:**
- `[key]` - Specific secret key to copy (omit when using --only or --except)

### Single Secret Copy

Copy individual secrets by specifying the key:

```bash
# Copy between stages
keep copy DB_PASSWORD --from=development --to=staging

# Copy with overwrite
keep copy DB_PASSWORD --from=development --to=staging --overwrite

# Dry run first
keep copy API_KEY --from=staging --to=production --dry-run

# Cross-vault copy
keep copy DB_PASSWORD --from=secretsmanager:development --to=ssm:production
```

### Bulk Copy with Patterns

Copy multiple secrets using pattern matching:

```bash
# Copy all database configs to production
keep copy --only="DB_*" --from=staging --to=production

# Copy everything except sensitive tokens
keep copy --except="*_SECRET,*_TOKEN" --from=development --to=staging

# Copy API keys only, with overwrite
keep copy --only="API_*" --from=development --to=production --overwrite

# Preview bulk operation with dry-run
keep copy --only="*" --from=staging --to=production --dry-run

# Combine patterns - copy DB configs except passwords
keep copy --only="DB_*" --except="*_PASSWORD" --from=dev --to=staging
```

**Pattern Matching:**
- `*` matches any characters
- `DB_*` matches all keys starting with "DB_"
- `*_HOST` matches all keys ending with "_HOST"
- `API_*_KEY` matches keys like "API_PUBLIC_KEY", "API_PRIVATE_KEY"
- Multiple patterns can be comma-separated: `"DB_*,API_*,REDIS_*"`

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
keep diff --stage=local,production --only="DB_*"

# Exclude specific keys
keep diff --stage=local,production --except="APP_DEBUG"
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
| `--file` | string | *stdout* | Output file path |
| `--append` | boolean | `false` | Append to output file instead of overwriting |
| `--overwrite` | boolean | `false` | Overwrite output file without confirmation |
| `--only` | string | | Comma-separated list of keys to include |
| `--except` | string | | Comma-separated list of keys to exclude |

### Direct Export Mode (no template)

Export all secrets from specified vaults:

```bash
# Basic .env export
keep export --stage=production --file=.env

# JSON export
keep export --stage=production --format=json --file=config.json

# Export from specific vaults
keep export --stage=production --vault=ssm,secretsmanager --file=.env

# Export with filtering
keep export --stage=production --only="API_*,DB_*" --file=.env
```

### Template Mode (with template)

Use templates with placeholder syntax `{vault:key}`:

```bash
# Basic template merge (preserves structure)
keep export --stage=production --template=.env.template --file=.env

# Template with all additional secrets appended
keep export --stage=production --template=.env.template --all --file=.env

# Template to JSON (parses and transforms)
keep export --stage=production --template=.env.template --format=json --file=config.json

# Multiple templates can be combined using standard tools
cat .env.base .env.prod | keep export --template=/dev/stdin --stage=production --file=.env

# Handle missing secrets gracefully
keep export --stage=production --template=.env.template --missing=skip --file=.env
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



## Getting Help

Each command includes detailed help:

```bash
keep --help
keep set --help
keep get --help
keep show --help
```

Use `--help` with any command to see its specific options and usage examples.
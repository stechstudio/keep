# CLI Command Reference

Complete reference for all Keep CLI commands with their options and usage examples.

## `keep init`

Initialize Keep settings and vault connections.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--no-interaction` | boolean | `false` | Run without prompts using defaults |

**Examples:**
```bash
# Interactive initialization
keep init

# Non-interactive initialization
keep init --no-interaction
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

## `keep env:add`

Add a custom env/environment beyond the standard ones (local, staging, production).

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--no-interaction` | boolean | `false` | Run without prompts |

**Arguments:**
- `[name]` - Stage name (prompted if not provided)

**Examples:**
```bash
# Interactive mode
keep env:add

# Direct mode with env name
keep env:add integration

# Add multiple custom envs
keep env:add demo
keep env:add qa
keep env:add hotfix

# Non-interactive mode
keep env:add sandbox --no-interaction
```

**Stage Name Requirements:**
- Must be lowercase
- Can contain letters, numbers, hyphens, and underscores
- Examples: `qa`, `demo`, `integration`, `sandbox`, `dev2`, `staging-eu`

## `keep workspace:configure`

Personalize your workspace by filtering which vaults and envs appear in commands and UI.

**Examples:**
```bash
keep workspace:configure
```

**Interactive prompts:**
- Select active vaults (from all configured vaults)
- Select active envs (from all configured envs)

**Notes:**
- By default, all vaults and envs are shown (no filtering)
- Workspace settings are personal and not committed to version control
- Filtering is cosmetic only - doesn't affect permissions or access
- Useful for focusing on specific environments or reducing clutter

## `keep verify`

Verify vault configuration, authentication, and permissions by running a comprehensive test matrix.

**Examples:**
```bash
# Verify all configured vaults
keep verify
```

**What it checks:**
- **Vault Configuration**: Validates that all configured vaults are properly set up
- **Authentication**: Tests that Keep can authenticate with AWS using current credentials
- **Permissions Matrix**: Runs through a complete test of read, write, and delete operations
- **Env Access**: Verifies access to all configured envs (local, staging, production, etc.)

**Output includes:**
- Connection status for each vault
- Authentication method being used (IAM role, profile, etc.)
- Permission check results for each operation
- Any errors or warnings about missing permissions

**Common use cases:**
```bash
# Run after initial setup
keep init
keep vault:add
keep verify

# Check before deploying to production
keep verify

# Troubleshoot permission issues
keep verify
```

## `keep info`

Display information about the Keep configuration, including version, paths, and settings.

**Examples:**
```bash
# Show Keep configuration info
keep info
```

**Information displayed:**
- **Keep Version**: Current version of the Keep package
- **Configuration Path**: Location of `.keep` directory
- **Settings Path**: Location of `settings.json`
- **Available Vaults**: List of configured vaults with their types
- **Configured Envs**: All available envs (local, staging, production, custom)
- **Default Vault**: The vault used when no `--vault` is specified
- **Cache Status**: Whether caching is enabled and cache location

**Common use cases:**
```bash
# Check Keep version
keep info

# Verify configuration after setup
keep init
keep info

# Debug path issues
keep info
```

## `keep set`

Create or update secrets in vaults.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--env` | string | *interactive* | Target env (local, staging, production) |
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
keep set API_KEY "abc123" --env=local

# Force overwrite
keep set API_KEY "new-value" --env=production --force

# Specify vault
keep set STRIPE_KEY "sk_live_..." --env=production --vault=secretsmanager
```

## `keep get`

Retrieve a specific secret from a vault.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--env` | string | *interactive* | Source env to retrieve from |
| `--vault` | string | *default vault* | Vault to retrieve the secret from |
| `--format` | string | `table` | Output format: `table`, `json`, `raw` |

**Arguments:**
- `[key]` - Secret key name (prompted if not provided)

**Examples:**
```bash
# Interactive mode
keep get

# Basic retrieval
keep get API_KEY --env=local

# JSON output
keep get STRIPE_KEY --env=production --format=json

# Raw format from specific vault
keep get CONFIG_JSON --env=staging --vault=ssm --format=raw
```

## `keep show`

Show all secrets from a vault and environment.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--env` | string | *interactive* | Stage to list secrets from |
| `--vault` | string | *default vault* | Vault to list secrets from |
| `--unmask` | boolean | `false` | Show actual secret values instead of masked |
| `--format` | string | `table` | Output format: `table`, `json`, `env` |
| `--only` | string | | Comma-separated list of keys to include |
| `--except` | string | | Comma-separated list of keys to exclude |

**Examples:**
```bash
# Basic listing (masked values)
keep show --env=local

# Show actual values
keep show --env=production --unmask

# Include only specific keys
keep show --env=production --only="NIGHTWATCH_*,MAIL_*"

# Exclude certain keys
keep show --env=local --except="DB_*,STRIPE_*"

# JSON output
keep show --env=staging --format=json

# From specific vault in env format
keep show --env=production --vault=secretsmanager --format=env
```

## `keep template:add`

Generate a template file from existing secrets in a environment.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `filename` | string | *required* | Template filename to create |
| `--env` | string | *required* | Stage to generate template from |
| `--vault` | string | *all vaults* | Specific vault to use |
| `--overwrite` | boolean | `false` | Overwrite existing template file |

### Examples

```bash
# Create template from production secrets
keep template:add .env.template --env=production

# Create from specific vault
keep template:add api.template --env=production --vault=ssm

# Overwrite existing template
keep template:add config.env --env=staging --overwrite
```

## `keep template:validate`

Validate template files for syntax and placeholder resolution.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `filename` | string | *required* | Template file to validate |
| `--env` | string | *optional* | Stage to validate against |

### Examples

```bash
# Validate template syntax and placeholders
keep template:validate .env.template

# Validate against specific env
keep template:validate .env.template --env=production
```

## `keep shell`

Start an interactive shell for Keep commands with persistent context.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--env` | string | *first configured env* | Initial env to use |
| `--vault` | string | *default vault* | Initial vault to use |

### Shell Mode Features

The interactive shell provides:
- **Persistent context**: No need to specify --env and --vault for each command
- **Command shortcuts**: Quick aliases for common commands
- **Context switching**: Easy switching between environments and vaults
- **Command history**: Access previous commands with arrow keys

### Shell Commands

**Context Management:**
```bash
keep> env production      # Switch to production env (alias: e)
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
keep shell --env=production --vault=ssm

# Interactive session
keep (ssm:production)> show
keep (ssm:production)> env development
✓ Switched to env: development
keep (ssm:development)> set API_KEY "dev-key"
keep (ssm:development)> copy API_KEY --to=production
keep (ssm:development)> exit
Goodbye!
```

### Tips
- Use partial names for envs/vaults (e.g., `e prod` for `env production`)
- All standard Keep commands work in the shell
- Commands automatically use the current context
- Use tab for basic command completion (if readline is available)

## `keep delete`

Remove secrets from vaults.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--env` | string | *interactive* | Stage to delete secret from |
| `--vault` | string | *default vault* | Vault to delete the secret from |
| `--force` | boolean | `false` | Delete without confirmation prompt |

**Arguments:**
- `[key]` - Secret key name (prompted if not provided)

**Examples:**
```bash
# Interactive mode
keep delete

# Basic deletion (with confirmation)
keep delete OLD_CONFIG --env=local

# Force deletion without prompt
keep delete TEMP_KEY --env=staging --force

# Delete from specific vault
keep delete LEGACY_SECRET --env=production --vault=ssm
```

## `keep rename`

Rename a secret while preserving its value and metadata.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--env` | string | *interactive* | Stage where the secret exists |
| `--vault` | string | *default vault* | Vault containing the secret |
| `--force` | boolean | `false` | Skip confirmation prompt |

**Arguments:**
- `old` - Current secret key name
- `new` - New secret key name

**Examples:**
```bash
# Rename with confirmation
keep rename DB_PASS DB_PASSWORD --env=local

# Force rename without prompt
keep rename OLD_API_KEY NEW_API_KEY --env=production --force

# Rename in specific vault
keep rename LEGACY_NAME MODERN_NAME --env=staging --vault=ssm
```

**Note:** Neither AWS SSM nor Secrets Manager support native rename operations. This command performs a copy + delete operation, which is the AWS-recommended approach.

## `keep search`

Search for text within secret values.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--env` | string | *interactive* | Stage to search in |
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
keep search "api.example.com" --env=production

# Search with actual values shown
keep search "localhost" --env=local --unmask

# Case-sensitive search
keep search "MySpecificValue" --env=staging --case-sensitive

# Search only in specific keys
keep search "postgres" --env=production --only="DB_*,DATABASE_*"

# JSON output
keep search "secret" --env=local --format=json
```

**Search Results:**
- Matched text is highlighted with a yellow background when using `--unmask`
- Shows the key name, masked/unmasked value, and revision for each match
- Returns success even when no matches are found

## `keep copy`

Copy secrets between environments or vaults. Supports both single secret and bulk operations with pattern matching.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--from` | string | *required* | Source context (env or vault:env) |
| `--to` | string | *required* | Destination context (env or vault:env) |
| `--overwrite` | boolean | `false` | Overwrite existing secrets without confirmation |
| `--dry-run` | boolean | `false` | Show what would be copied without making changes |
| `--only` | string | | Pattern for bulk copy - include only matching keys (e.g., `DB_*`) |
| `--except` | string | | Pattern for bulk copy - exclude matching keys (e.g., `*_SECRET`) |

**Arguments:**
- `[key]` - Specific secret key to copy (omit when using --only or --except)

### Single Secret Copy

Copy individual secrets by specifying the key:

```bash
# Copy between environments
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

Show differences between environments and vaults.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--vault` | string | *all vaults* | Comma-separated list of vaults to compare |
| `--env` | string | *all environments* | Comma-separated list of envs to compare |
| `--unmask` | boolean | `false` | Show actual secret values (not masked) |
| `--only` | string | | Comma-separated list of keys to include |
| `--except` | string | | Comma-separated list of keys to exclude |

**Examples:**
```bash
# Compare all configured vaults and envs
keep diff

# Compare specific envs
keep diff --env=staging,production

# Show actual values
keep diff --env=staging,production --unmask

# Compare specific keys only
keep diff --env=local,production --only="DB_*"

# Exclude specific keys
keep diff --env=local,production --except="APP_DEBUG"
```

## `keep import`

Import secrets from `.env` files.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--env` | string | *interactive* | Target env to import secrets into |
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
keep import .env.development --env=local

# Import with existing secret protection
keep import production.env --env=production --skip-existing

# Force overwrite existing secrets
keep import staging.env --env=staging --overwrite

# Dry run to preview import
keep import .env --env=local --dry-run

# Import only specific keys
keep import secrets.json --env=production --only="API_KEY,DB_PASSWORD"

# Import from stdin
cat .env | keep import --env=local

# Exclude sensitive keys
keep import .env --env=local --except="PRIVATE_KEY"
```

## `keep export`

Export secrets from vaults with optional template processing.

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--env` | string | *interactive* | Stage to export secrets from |
| `--vault` | string | *auto-discover* | Vault(s) to export from (comma-separated) |
| `--format` | string | `env` | Output format: `env`, `json`, `csv` |
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
keep export --env=production --file=.env

# JSON export
keep export --env=production --format=json --file=config.json

# CSV export for spreadsheets
keep export --env=production --format=csv --file=secrets.csv

# Export from specific vaults
keep export --env=production --vault=ssm,secretsmanager --file=.env

# Export with filtering
keep export --env=production --only="API_*,DB_*" --file=.env
```

### Template Mode (with template)

Use templates with placeholder syntax `{vault:key}`:

```bash
# Basic template merge (preserves structure)
keep export --env=production --template=.env.template --file=.env

# Template with all additional secrets appended
keep export --env=production --template=.env.template --all --file=.env

# Template to JSON (parses and transforms)
keep export --env=production --template=.env.template --format=json --file=config.json

# Multiple templates can be combined using standard tools
cat .env.base .env.prod | keep export --template=/dev/stdin --env=production --file=.env

# Handle missing secrets gracefully
keep export --env=production --template=.env.template --missing=skip --file=.env
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



## `keep run`

Execute subprocesses with secrets injected as environment variables (diskless).

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--vault` | string | *interactive* | Vault to fetch secrets from |
| `--env` | string | *interactive* | Stage to use |
| `--template` | string | | Template file path, or auto-discover if empty |
| `--only` | string | | Include only matching keys (patterns) |
| `--except` | string | | Exclude matching keys (patterns) |
| `--no-inherit` | boolean | `false` | Don't inherit current environment |

**Arguments:**
- `cmd...` - Command and arguments to run (after `--`)

**Examples:**
```bash
# Run with all secrets from vault
keep run --vault=ssm --env=production -- npm start

# Use template for selective secrets + static config
keep run --vault=ssm --env=production --template=env/prod.env -- npm start

# Auto-discover template (looks for env/{env}.env)
keep run --vault=ssm --env=production --template -- npm start

# Filter secrets by pattern
keep run --vault=ssm --env=production --only='DB_*' -- npm run migrate

# Clean environment (no inheritance)
keep run --vault=ssm --env=production --no-inherit -- node server.js

# Laravel config cache (one-time injection)
keep run --vault=ssm --env=production -- php artisan config:cache
```

**Key Features:**
- Secrets never touch disk - only exist in subprocess memory
- Exit codes propagated for CI/CD compatibility
- TTY support for interactive commands
- Works with templates for complete configuration (secrets + static values)

See [Runtime Secrets Injection](/guide/deployment/runtime-injection) for detailed documentation.

## Getting Help

Each command includes detailed help:

```bash
keep --help
keep set --help
keep get --help
keep show --help
```

Use `--help` with any command to see its specific options and usage examples.
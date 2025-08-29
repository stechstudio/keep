# Exporting to .env

This guide covers generating configuration files for deployment using exports and templates. These are the primary ways to get your secrets into running applications.

## Exporting Secrets

The `keep export` command generates configuration files from your secrets in various formats, perfect for application deployment.

### Basic Usage

```bash
# Export to .env file
keep export --stage=production --output=.env

# Export as JSON
keep export --stage=development --format=json

# Export to stdout (default)
keep export --stage=staging
```

### Command Reference: `keep export`

| Option        | Type    | Default | Description |
|---------------|---------|---------|-------------|
| `--stage`     | string  | *interactive* | Stage to export secrets from |
| `--vault`     | string  | *default vault* | Vault to export secrets from |
| `--format`    | string  | `env` | Output format: `env`, `json` |
| `--file`      | string  | *stdout* | Output file path |
| `--append`    | boolean | `false` | Append to output file instead of overwriting |
| `--overwrite` | boolean | `false` | Overwrite output file without confirmation |
| `--only`      | string  | | Comma-separated list of keys to include |
| `--except`    | string  | | Comma-separated list of keys to exclude |

**Examples:**
```bash
# Basic .env export
keep export --stage=production --file=.env

# JSON export for configuration management
keep export --stage=production --format=json --file=config.json

# Export only API-related secrets
keep export --stage=production --only="API_*" --file=api.env

# Export all except certain keys
keep export --stage=production --except="PRIVATE_KEY,SECRET_TOKEN" --file=.env

# Export to stdout for piping
keep export --stage=production --format=json | jq '.API_KEY'
```

## Template-Based Export

The `keep export --template` command combines secrets with template files, allowing you to create complete configuration files with both secrets and static values.

### Basic Usage

```bash
# Merge template with secrets
keep export --template=.env.template --stage=production --file=.env

# Output to stdout
keep export --template=.env.template --stage=development

# Include all secrets beyond template placeholders
keep export --template=.env.template --stage=production --all --file=.env
```

### Template Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--template` | string | | Template file with placeholders (required) |
| `--all` | boolean | `false` | Also append non-placeholder secrets |
| `--missing` | string | `fail` | Handle missing secrets: `fail`, `skip`, `blank`, `remove` |
| `--format` | string | `env` | Output format: `env` (preserves structure), `json` (parses data) |

## Template Syntax

A template is a .env file where some of the values are placeholders in curly braces `{}`. For example:

```bash
# Specify the vault slug and secret name
API_KEY={ssm:service-api-key}

# If the key name matches the secret name, you can omit the secret name
DB_PASSWORD={ssm}

# Multiple vaults are supported if configured
REDIS_URL={secretsmanager:REDIS_URL}
```

**Examples:**
```bash
# Basic template merge (preserves structure)
keep export --template=.env.template --stage=production --file=.env

# Handle missing secrets gracefully
keep export --template=.env.template --stage=development --missing=skip --file=.env

# Remove lines with missing secrets
keep export --template=.env.template --stage=staging --missing=remove --file=.env

# Template to JSON (parses and transforms data)
keep export --template=.env.template --stage=production --format=json --file=config.json

# Template with all additional secrets
keep export --template=.env.template --stage=production --all --file=.env
```

### Template Validation

It's a good practice to validate your templates before deployment to ensure all placeholders can be resolved.

```bash
# Validate templates before deployment
keep template:validate app.template --stage=production
```

<!-- Future enhancement: Cache Export
## Cache Export

The export command can also create encrypted cache files for Laravel integration using the `--cache` flag:

```bash
# Export secrets to encrypted cache file
keep export --stage=production --cache

# Export from specific vaults to cache
keep export --stage=production --vault=ssm,secretsmanager --cache

# Cache with filters
keep export --stage=production --only="API_*,DB_*" --cache
```

The `--cache` flag creates an encrypted `.keep.php` file in `.keep/cache/` and updates your `.env` file with the required `KEEP_CACHE_KEY_PART` for decryption.
-->
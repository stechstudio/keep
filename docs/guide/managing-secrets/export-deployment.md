# Export & Deployment

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
| `--output`    | string  | *stdout* | Output file path |
| `--append`    | boolean | `false` | Append to output file instead of overwriting |
| `--overwrite` | boolean | `false` | Overwrite output file without confirmation |
| `--only`      | string  | | Comma-separated list of keys to include |
| `--except`    | string  | | Comma-separated list of keys to exclude |

**Examples:**
```bash
# Basic .env export
keep export --stage=production --output=.env

# JSON export for configuration management
keep export --stage=production --format=json --output=config.json

# Export only API-related secrets
keep export --stage=production --only="API_*" --output=api.env

# Export all except certain keys
keep export --stage=production --except="PRIVATE_KEY,SECRET_TOKEN" --output=.env

# Export to stdout for piping
keep export --stage=production --format=json | jq '.API_KEY'
```

## Template Merging

The `keep merge` command combines secrets with template files, allowing you to create complete configuration files with both secrets and static values.

### Basic Usage

```bash
# Merge template with secrets
keep merge .env.template --stage=production --output=.env

# Output to stdout
keep merge .env.template --stage=development
```

### Command Reference: `keep merge`

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `--stage` | string | *interactive* | Stage to get secrets from |
| `--vault` | string | *default vault* | Vault to get secrets from |
| `--output` | string | *stdout* | Output file path |
| `--append` | boolean | `false` | Append to output file instead of overwriting |
| `--overwrite` | boolean | `false` | Overwrite output file without confirmation |
| `--missing` | string | `fail` | How to handle missing secrets: `fail`, `skip`, `blank`, `remove` |

**Arguments:**
- `[template]` - Path to template file (required)

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
# Basic template merge
keep merge .env.template --stage=production --output=.env

# Handle missing secrets gracefully
keep merge .env.template --stage=development --missing=skip --output=.env

# Remove lines with missing secrets
keep merge .env.template --stage=staging --missing=remove --output=.env

# Output to stdout for verification
keep merge .env.template --stage=production | grep -v "^#"
```

### Template Validation

It's a good practice to validate your templates before deployment to ensure all placeholders can be resolved.

```bash
# Validate templates before deployment
keep template:validate app.template --stage=production
```
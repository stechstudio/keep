# Keep Run Command

The `keep run` command allows you to execute subprocesses with secrets injected as environment variables, enabling diskless secret management for applications.

## Basic Usage

```bash
# Run a command with all secrets from a vault
keep run --vault=<vault> --stage=<stage> -- <command> [arguments]

# Example
keep run --vault=aws-ssm --stage=production -- npm run build
```

## Features

### Template-Based Secret Selection

Use template files to select specific secrets:

```bash
# Use a specific template file
keep run --vault=aws-ssm --stage=production --template=env/prod.env -- ./app

# Auto-discover template based on stage name (looks for env/{stage}.env)
keep run --vault=aws-ssm --stage=production --template -- ./app
```

Template format:
```env
# env/production.env
DB_HOST={vault-name:DB_HOST}
DB_PASSWORD={vault-name:DB_PASSWORD}
API_KEY={vault-name:API_KEY}
```

### Filtering Secrets

Filter which secrets are injected:

```bash
# Only include secrets matching a pattern
keep run --vault=aws-ssm --stage=production --only='DB_*' -- ./app

# Exclude secrets matching a pattern  
keep run --vault=aws-ssm --stage=production --except='DEBUG_*' -- ./app
```

### Environment Inheritance

By default, the current environment is inherited. Use `--no-inherit` for a clean environment:

```bash
# Run with only the injected secrets (no inherited environment)
keep run --vault=aws-ssm --stage=production --no-inherit -- ./app
```

## Use Cases

### Laravel Configuration Cache

```bash
# Generate Laravel config cache with secrets injected
keep run --vault=aws-ssm --stage=production -- php artisan config:cache
```

### Build Processes

```bash
# Run build with API keys and tokens injected
keep run --vault=aws-ssm --stage=production --only='*_TOKEN' -- npm run build
```

### Database Migrations

```bash
# Run migrations with database credentials injected
keep run --vault=aws-ssm --stage=production --only='DB_*' -- php artisan migrate
```

## Exit Codes

The `keep run` command propagates the exit code from the subprocess, making it suitable for CI/CD pipelines:

```bash
keep run --vault=aws-ssm --stage=production -- ./test.sh
if [ $? -eq 0 ]; then
    echo "Tests passed"
else
    echo "Tests failed"
fi
```

## Security Notes

- Secrets are injected as environment variables only for the duration of the subprocess
- The subprocess inherits the injected environment but secrets are not written to disk
- Use `--no-inherit` when you want to ensure no unintended environment variables are passed
- TTY mode is automatically enabled for interactive commands when available
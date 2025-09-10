# CLI Overview

Keep's CLI is primarily designed for scripting, CI/CD pipelines, and automated deployments. For daily secret management, you may find the [Web UI](/guide/web-ui/) or [Interactive Shell](/guide/shell/) easier to use.

## When to Use CLI

**Ideal for:**
- CI/CD pipelines
- Automated deployments  
- Batch operations
- Scripting and automation
- Runtime secrets injection
- Direct command execution

**Also consider:**
- **[Web UI](/guide/web-ui/)** - Visual interface for browsing and editing secrets
- **[Interactive Shell](/guide/shell)** - Tab completion and exploratory operations
- **CLI** - Quick one-off commands and scripting

## Common Automation Patterns

### CI/CD Pipeline Integration

```bash
# GitHub Actions example
- name: Deploy with secrets
  run: |
    keep run --vault=ssm --env=production -- npm run deploy

# Jenkins example
sh 'keep export --env=production --file=.env --overwrite'
sh 'docker build --secret id=env,src=.env .'
sh 'rm -f .env'  # Clean up
```

### Bulk Operations

```bash
# Copy all API secrets to new environment
keep copy --only="API_*" --from=staging --to=production

# Import from backup
keep import backup.env --env=disaster-recovery --overwrite

# Export filtered secrets
keep export --env=production --only="PUBLIC_*" --file=public.env
```

### Environment Provisioning

```bash
#!/bin/bash
# Setup new environment with secrets

ENV=$1
if [ -z "$ENV" ]; then
  echo "Usage: $0 <env>"
  exit 1
fi

# Copy base configuration
keep copy --only="*" --from=template --to=$ENV

# Set environment-specific values
keep set DATABASE_URL "postgres://db-$ENV.internal/app" --env=$ENV --force
keep set API_URL "https://api-$ENV.example.com" --env=$ENV --force

# Validate
keep show --env=$ENV
```

## Scripting Best Practices

### Exit on Errors
```bash
#!/bin/bash
set -euo pipefail

keep set API_KEY "$NEW_KEY" --env=production --force
keep run --env=production -- npm run deploy
```

### Use Force Flags for Automation
```bash
# Avoid interactive prompts in scripts
keep set KEY "value" --env=production --force
keep delete OLD_KEY --env=staging --force
keep export --env=production --file=.env --overwrite
```

### Validate Before Production Changes
```bash
# Always dry-run first
keep copy --only="*" --from=staging --to=production --dry-run

# Then execute if satisfied
keep copy --only="*" --from=staging --to=production --overwrite
```

### Handle Secrets Securely
```bash
# Never log secret values
keep show --env=production  # Masked by default

# Clean up exported files
keep export --env=production --file=.env
trap "rm -f .env" EXIT
# ... use .env file ...
```

## Runtime Injection (Recommended)

For production deployments, use `keep run` to inject secrets without writing to disk:

```bash
# Laravel deployment
keep run --vault=ssm --env=production -- php artisan config:cache

# Node.js application
keep run --vault=ssm --env=production --template -- npm start

# Docker compose
keep run --vault=ssm --env=production -- docker-compose up -d
```

See [Deployment & Runtime](/guide/deployment/) for comprehensive deployment strategies.

## Full Command Reference

For detailed command syntax and options, see the [CLI Command Reference](./reference).

### Quick Command Overview

| Command | Purpose | Common Use Case |
|---------|---------|-----------------|
| `keep set` | Create/update secrets | Initial setup |
| `keep get` | Retrieve single secret | Debugging |
| `keep show` | List all secrets | Verification |
| `keep copy` | Copy between environments/vaults | Promotion |
| `keep diff` | Compare environments | Pre-deployment check |
| `keep import` | Import from files | Migration |
| `keep export` | Export to files | Legacy apps |
| `keep run` | Runtime injection | Production deployment |
| `keep delete` | Remove secrets | Cleanup |
| `keep shell` | Interactive mode | Exploration |
| `keep ui` | Web interface | Management |

## Integration Examples

### GitHub Actions
```yaml
- name: Deploy with Keep
  run: |
    keep run --vault=ssm --env=${{ github.ref_name }} -- ./deploy.sh
```

### GitLab CI
```yaml
deploy:
  script:
    - keep export --env=production --file=.env --overwrite
    - docker build -t myapp .
    - rm -f .env
```

### Jenkins Pipeline
```groovy
stage('Deploy') {
  sh 'keep run --vault=ssm --env=production -- npm run deploy'
}
```

### Kubernetes Secrets
```bash
# Generate secret manifest
keep export --env=production --format=json | \
  kubectl create secret generic app-secrets --from-file=secrets.json=/dev/stdin
```

## Next Steps

- [Web UI Guide](/guide/web-ui/) - Visual interface for managing secrets
- [Interactive Shell](/guide/shell/) - Tab completion and interactive operations
- [Deployment Strategies](/guide/deployment/) - Production deployment patterns
- [CLI Command Reference](./reference) - Complete command documentation
# Examples

## Available Examples

<!-- Laravel Integration deferred to future release
### [Laravel Integration](./laravel)
Integrate Keep with Laravel applications using the service provider and helper functions.
-->

### [CI/CD Workflows](./ci-cd)
Use Keep in GitHub Actions, GitLab CI, and other automation pipelines.

### [Multi-Environment Setup](./multi-environment)
Organize secrets across local, staging, and production environments.

### [AWS Setup](./aws-setup)
Configure IAM roles, SSM Parameter Store, and Secrets Manager for Keep.

## Quick Examples

### Local to Production
```bash
# Configure project
keep configure
keep vault:add

# Set local development secrets
keep set API_KEY "dev-key" --stage=local
keep set DB_HOST "localhost" --stage=local
keep set DB_PASSWORD "dev-pass" --stage=local

# Copy single secret to staging
keep copy API_KEY --from=local --to=staging

# Bulk copy all database configs to staging
keep copy --only="DB_*" --from=local --to=staging

# Promote everything to production (preview first)
keep copy --only="*" --from=staging --to=production --dry-run
keep copy --only="*" --from=staging --to=production --overwrite
```

### Using Templates
```bash
# Create template
echo "API_KEY={ssm:API_KEY}" > .env.template

# Generate config
keep export --template=.env.template --stage=production --output=.env
```
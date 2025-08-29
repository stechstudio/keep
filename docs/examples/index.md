# Examples

## Available Examples

<!-- Laravel Integration deferred to future release
### [Laravel Integration](./laravel)
Integrate Keep with Laravel applications using the service provider and helper functions.
-->

### [CI/CD Workflows](./ci-cd)
Use Keep in GitHub Actions, GitLab CI, and other automation pipelines.

### [Multi-Environment Setup](./multi-environment)
Organize secrets across development, staging, and production environments.

### [AWS Setup](./aws-setup)
Configure IAM roles, SSM Parameter Store, and Secrets Manager for Keep.

## Quick Examples

### Development to Production
```bash
# Configure project
keep configure
keep vault:add

# Set development secret
keep set API_KEY "dev-key" --stage=development

# Copy to staging
keep copy API_KEY --from=development --to=staging

# Promote to production
keep copy API_KEY --from=staging --to=production
```

### Using Templates
```bash
# Create template
echo "API_KEY={ssm:API_KEY}" > .env.template

# Generate config
keep export --template=.env.template --stage=production --output=.env
```
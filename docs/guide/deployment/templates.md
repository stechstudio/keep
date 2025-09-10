# Managing Templates

Templates define your complete application configuration - both secrets and non-sensitive values. They serve as the source of truth for what your application needs, whether you're exporting to files or injecting at runtime.

## Why Templates?

Templates solve multiple problems:
- **Complete Configuration**: Mix secrets with static values in one place
- **Selective Secrets**: Choose exactly which secrets to include
- **Environment Variables**: Add non-sensitive config that doesn't belong in vaults
- **Consistency**: Same template works for both file export and runtime injection

## Template Syntax

```bash
# Static configuration values
APP_NAME=MyApplication
APP_ENV=production
LOG_LEVEL=info
TIMEZONE=UTC

# Infrastructure settings (not sensitive)
REDIS_HOST=redis.internal
QUEUE_CONNECTION=redis

# Secrets from vaults
DATABASE_URL={ssm:DATABASE_URL}
REDIS_PASSWORD={ssm:REDIS_PASSWORD}
API_KEY={ssm:API_KEY}

# Path-based secrets
STRIPE_KEY={ssm:payments/stripe/key}

# Multiple vaults
AWS_ACCESS_KEY={ssm:AWS_ACCESS_KEY}
GITHUB_TOKEN={secretsmanager:GITHUB_TOKEN}
```

Templates are your application's complete environment configuration - Keep handles injecting the secret placeholders while preserving your static values.

## Creating Templates

### From Existing Secrets

Generate templates automatically from your vault:

```bash
# Create template from all secrets in an env
keep template:add production.env --env=production

# From specific vault
keep template:add api.env --env=production --vault=ssm

# Overwrite existing template
keep template:add config.env --env=staging --overwrite
```

### Manual Creation

Create templates that define exactly what your application needs:

```bash
cat > env/production.env << 'EOF'
# Application
APP_NAME=MyApp
APP_ENV=production
APP_DEBUG=false

# Database (from vault)
DB_CONNECTION=mysql
DB_HOST={ssm:DB_HOST}
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME={ssm:DB_USERNAME}
DB_PASSWORD={ssm:DB_PASSWORD}

# Cache (mixed static/secret)
CACHE_DRIVER=redis
REDIS_HOST=redis.internal
REDIS_PASSWORD={ssm:REDIS_PASSWORD}

# External Services
STRIPE_KEY={secretsmanager:STRIPE_KEY}
STRIPE_SECRET={secretsmanager:STRIPE_SECRET}
EOF
```

### Template Organization

Organize templates by env to match your deployment pipeline:

```
env/
├── production.env     # Production secrets
├── staging.env        # Staging environment  
├── local.env          # Local development
├── ci.env             # CI/CD pipeline
└── test.env           # Test environment
```

Keep templates environment-specific rather than service-specific - each environment defines all the secrets your application needs for that environment.

## Validating Templates

Ensure templates can be resolved before deployment:

```bash
# Validate for specific env
keep template:validate env/production.env --env=production

# Check all placeholders without env
keep template:validate env/production.env

# Validate multiple templates
for template in env/*.env; do
    keep template:validate "$template" --env=production
done
```

Validation checks:
- Placeholder syntax is correct
- Referenced vaults exist
- Secrets are accessible (with env specified)
- No circular references

## Using Templates

### With Export Command

Export resolved templates to files:

```bash
# Basic export
keep export --template=env/production.env --env=production --file=.env

# Append non-template secrets
keep export --template=env/production.env --env=production --all --file=.env

# Handle missing secrets
keep export --template=env/production.env --env=staging \
  --missing=skip --file=.env
```

### With Run Command

Inject only template-defined secrets at runtime:

```bash
# Use specific template
keep run --vault=ssm --env=production \
  --template=env/production.env -- npm start

# Auto-discover template (env/{env}.env)
keep run --vault=ssm --env=production --template -- npm start
```

## Template Strategies

### Environment-Specific Templates

Create separate templates for each environment:

```bash
# env/production.env
LOG_LEVEL=error
DEBUG=false
DATABASE_URL={ssm:prod/database/url}
STRIPE_KEY={ssm:prod/stripe/key}

# env/staging.env  
LOG_LEVEL=info
DEBUG=true
DATABASE_URL={ssm:staging/database/url}
STRIPE_KEY={ssm:staging/stripe/test-key}

# env/local.env
LOG_LEVEL=debug
DEBUG=true
DATABASE_URL=postgresql://localhost/myapp_dev
STRIPE_KEY={ssm:dev/stripe/test-key}
```

### Template Overrides

For special cases, you can layer templates using the append flag:

```bash
# Start with env template
keep export --template=env/production.env --env=production --file=.env

# Add temporary overrides or hotfixes
keep export --template=env/hotfix.env --env=production --append --file=.env
```

This is rarely needed - a well-designed env template should contain all necessary secrets for that environment.

## Missing Secret Handling

```bash
# Fail on missing secrets (default)
keep export --template=env/prod.env --env=production --missing=fail

# Skip - leaves placeholders unchanged
keep export --template=env/prod.env --env=production --missing=skip

# Blank - sets to empty value
keep export --template=env/prod.env --env=production --missing=blank

# Remove - removes entire line
keep export --template=env/prod.env --env=production --missing=remove
```

## Template Examples

### Laravel Application

```bash
# env/laravel.env
APP_NAME="${APP_NAME:-Laravel}"
APP_ENV=production
APP_KEY={ssm:APP_KEY}
APP_DEBUG=false
APP_URL=https://example.com

DB_CONNECTION=mysql
DB_HOST={ssm:DB_HOST}
DB_PORT=3306
DB_DATABASE={ssm:DB_DATABASE}
DB_USERNAME={ssm:DB_USERNAME}
DB_PASSWORD={ssm:DB_PASSWORD}

CACHE_DRIVER=redis
REDIS_HOST={ssm:REDIS_HOST}
REDIS_PASSWORD={ssm:REDIS_PASSWORD}

MAIL_DRIVER=smtp
MAIL_HOST={ssm:MAIL_HOST}
MAIL_USERNAME={ssm:MAIL_USERNAME}
MAIL_PASSWORD={ssm:MAIL_PASSWORD}
```

**Laravel Tip**: When using `config:cache`, you only need to inject secrets once:
```bash
# Inject secrets and cache configuration
keep run --vault=ssm --env=production --template=env/laravel.env -- php artisan config:cache

# ALL subsequent Laravel processes use cached config (no injection needed)
php artisan migrate --force
php artisan route:cache
php artisan queue:restart  # New workers will also use cached config
```

### Node.js Microservice

```bash
# env/node-service.env
NODE_ENV=production
PORT=3000

# Database
DATABASE_URL={ssm:DATABASE_URL}
DB_POOL_MIN=2
DB_POOL_MAX=10

# Authentication
JWT_SECRET={ssm:JWT_SECRET}
JWT_EXPIRY=1h

# External APIs
STRIPE_KEY={secretsmanager:STRIPE_KEY}
STRIPE_WEBHOOK_SECRET={secretsmanager:STRIPE_WEBHOOK_SECRET}
SENDGRID_API_KEY={secretsmanager:SENDGRID_API_KEY}

# Monitoring
SENTRY_DSN={ssm:SENTRY_DSN}
NEW_RELIC_KEY={ssm:NEW_RELIC_KEY}
```

### Docker Compose

```bash
# env/docker.env
# Database Service
POSTGRES_USER={ssm:DB_USER}
POSTGRES_PASSWORD={ssm:DB_PASSWORD}
POSTGRES_DB=myapp

# Redis Service
REDIS_PASSWORD={ssm:REDIS_PASSWORD}

# Application
DATABASE_URL=postgresql://${POSTGRES_USER}:${POSTGRES_PASSWORD}@db:5432/${POSTGRES_DB}
REDIS_URL=redis://:${REDIS_PASSWORD}@redis:6379
SECRET_KEY={ssm:SECRET_KEY}
```

## Best Practices

- **Version Control**: Commit templates (they contain no secrets, only placeholders)
- **Comments**: Document what each secret/variable is for
- **Validation**: Always validate before deployment: `keep template:validate env/prod.env --env=production`
- **Organization**: One template per env, containing complete configuration
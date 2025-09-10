# Runtime Secrets Injection

Keep enables secure runtime injection of secrets into your applications without writing them to disk. This approach is ideal for production environments where you need secrets available to your application processes without persisting them in files.

## Overview

The `keep run` command executes subprocesses with secrets injected as environment variables, providing a diskless solution for secret management. This is particularly valuable for:

- **Build processes** that need API keys and tokens
- **Application servers** requiring database credentials
- **CI/CD pipelines** with deployment secrets
- **Container orchestration** where secrets shouldn't be baked into images

## How It Works

```bash
keep run --vault=<vault> --env=<env> -- <command> [arguments]
```

Keep fetches secrets from your vault, injects them as environment variables, and executes your command. The secrets exist only in memory for the duration of the process.

## Laravel Applications

Laravel requires secrets only once - during configuration caching. After that, all processes use the cached config:

```bash
#!/bin/bash
# deploy.sh

# Inject secrets and cache configuration (only injection needed!)
keep run --vault=ssm --env=production -- php artisan config:cache

# All subsequent commands use cached config - no injection required
php artisan migrate --force
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

Once cached, Laravel completely ignores environment variables, reading everything from `bootstrap/cache/config.php`.

## Symfony Applications

Symfony compiles environment variables into the container during cache warmup. After compilation, all commands use the cached container:

```bash
# Inject secrets during container compilation (only injection needed!)
keep run --vault=ssm --env=production -- php bin/console cache:warmup --env=prod

# All subsequent commands use compiled container - no injection required
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console messenger:consume async
```

Once compiled, Symfony reads configuration from `var/cache/prod/` and never accesses environment variables.

## Node.js Applications

```bash
# Build with API keys
keep run --vault=ssm --env=production --only='*_API_KEY,*_TOKEN' -- npm run build

# Start server with all secrets
keep run --vault=ssm --env=production -- node server.js

# Use PM2 process manager
keep run --vault=ssm --env=production -- pm2 start app.js

# Template for specific secrets
keep run --vault=ssm --env=production --template=env/prod.env -- npm start
```

Your Node.js app reads from environment variables as usual:

```javascript
const config = {
    port: process.env.PORT || 3000,
    dbUrl: process.env.DATABASE_URL,
    apiKey: process.env.API_KEY
};
```

## Python Applications

```bash
# Django migrations
keep run --vault=ssm --env=production -- python manage.py migrate

# Django with Gunicorn
keep run --vault=ssm --env=production -- gunicorn myapp.wsgi:application

# Flask development
keep run --vault=ssm --env=local -- flask run

# Flask production
keep run --vault=ssm --env=production -- gunicorn app:app --bind 0.0.0.0:8000
```

## Docker Integration

```bash
# Build with secrets (multi-env recommended)
keep run --vault=ssm --env=production -- docker build \
  --build-arg NPM_TOKEN=$NPM_TOKEN -t myapp:latest .

# Run container
keep run --vault=ssm --env=production -- docker run \
  -e DATABASE_URL=$DATABASE_URL myapp:latest

# Docker Compose
keep run --vault=ssm --env=production -- docker-compose up
```

With Docker Compose, environment variables are automatically passed through when listed without values.

## Template-Based Injection

```bash
# Generate template from existing secrets in env/production.env
keep template:add --env=production

# Auto-discover template (looks for env/{env}.env)
keep run --vault=ssm --env=production --template -- npm start

# Use specific template
keep run --vault=ssm --env=production --template=env/prod.env -- npm start
```

See [Managing Templates](./templates.md) for detailed template documentation.

## Filtering Secrets

Control which secrets are injected using patterns:

```bash
# Only database secrets
keep run --vault=ssm --env=production --only='DB_*,DATABASE_*' -- npm run migrate

# Exclude sensitive keys
keep run --vault=ssm --env=production --except='*_PRIVATE_KEY,*_SECRET' -- npm run build

# Multiple patterns
keep run --vault=ssm --env=production --only='API_*,SERVICE_*' --except='*_TEST' -- npm start
```

## CI/CD Integration

```yaml
# GitHub Actions
- name: Build and Deploy
  run: |
    keep run --vault=ssm --env=production -- npm run build
    keep run --vault=ssm --env=production -- npm run deploy

# GitLab CI
deploy:
  script:
    - keep run --vault=ssm --env=$CI_ENVIRONMENT_NAME -- ./deploy.sh
```

## Additional Options

```bash
# Clean environment (no inheritance)
keep run --vault=ssm --env=production --no-inherit -- node server.js

# Exit codes are propagated for CI/CD
keep run --vault=ssm --env=production -- npm test || exit 1
```

## Security Notes

- Secrets never touch disk - only exist in process memory
- Process isolation ensures secrets don't leak between processes
- All access is logged in your vault's audit trail
- TTY mode automatically enabled for interactive commands

## Common Patterns

```bash
# Development
keep run --vault=ssm --env=local --template -- npm run dev

# Production deployment
keep run --vault=ssm --env=production --template --no-inherit -- ./deploy.sh

# Testing with filtered secrets
keep run --vault=ssm --env=test --only='TEST_*,DB_*' -- npm test
```
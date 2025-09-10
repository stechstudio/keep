# Deployment & Runtime

Keep provides multiple strategies for integrating secrets into your production deployments. Whether you need runtime injection, file-based configuration, or template management, Keep has you covered.

## Deployment Workflow

### 1. [Managing Templates](./templates.md) (Optional)
**Preparation step** - Define your complete application configuration with both secrets and static values.

Templates provide:
- Complete environment configuration in one place
- Mix of secrets (placeholders) and non-sensitive values
- Selective secret inclusion
- Consistency across deployment methods

```bash
# Create template from existing secrets
keep template:add production.env --env=production
```

### 2. [Runtime Secrets Injection](./runtime-injection.md)
**Recommended for production** - Execute applications with secrets injected as environment variables without writing to disk.

Best for:
- Laravel's `config:cache` command
- Docker containers
- CI/CD pipelines
- Any production deployment

```bash
# With template (includes static values)
keep run --vault=ssm --env=production --template -- npm start

# Without template (secrets only)
keep run --vault=ssm --env=production -- npm start
```

### 3. [Exporting to Files](./exporting.md)
**Alternative approach** - Generate `.env` files when file-based configuration is required.

Best for:
- Local development
- Legacy applications
- Tools requiring file input

```bash
# With template
keep export --template=env/production.env --env=production --file=.env

# Without template
keep export --env=production --file=.env
```

## When to Use Each Approach

**Runtime Injection** (Production)
- Highest security - no files written
- Perfect for CI/CD and containerized apps
- Use with templates for complete configuration

**File Export** (Development)  
- Convenient for local development
- Works with existing dev tools
- Delete files after use in production

## Framework Examples

### Laravel
```bash
# One-time injection for config cache
keep run --vault=ssm --env=production -- php artisan config:cache
# All subsequent commands use cached config
php artisan migrate --force
```

### Node.js
```bash
keep run --vault=ssm --env=production --template -- npm start
```

### Docker
```bash
keep run --vault=ssm --env=production -- docker-compose up
```

## Template Organization

```
env/
├── production.env     # Complete prod configuration
├── staging.env        # Staging with debug enabled
├── local.env          # Local dev settings
└── ci.env             # CI/CD requirements
```

One template per env, containing both secrets (as placeholders) and static configuration.


## Next Steps

- Learn about [Runtime Secrets Injection](./runtime-injection.md)
- Set up [Templates](./templates.md) for your applications
- Understand [File Export](./exporting.md) options
- Review [Security Best Practices](/guide/reference/security-architecture)
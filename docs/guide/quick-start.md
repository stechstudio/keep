# Quick Start

This guide gets you managing secrets with Keep in 5 minutes. We'll assume you've already run `keep configure` and set up your first vault (see [Configuration](./configuration) if you haven't).

## Setting Your First Secret

```bash
# Run interactively - you'll be prompted for key, value, and stage
keep set

# Or specify everything directly
keep set DB_PASSWORD "super-secret-password" --stage=development
```

## Viewing Your Secrets

```bash
# List all secrets (values are masked by default)
keep list --stage=development

# Get a specific secret
keep get DB_PASSWORD --stage=development

# Show the actual value (unmasked)
keep get DB_PASSWORD --stage=development --unmask
```

## Export to .env File

```bash
# Export secrets to a .env file
keep export --stage=development --output=.env
```

This creates a `.env` file with your secrets:
```env
DB_PASSWORD="super-secret-password"
```

## What's Next?

You're now managing secrets with Keep! Here's where to go next:

- **[Managing Secrets](./managing-secrets/)** - Comprehensive guide to secret operations
- **[Vaults](./vaults)** - Add production vaults (AWS SSM, Secrets Manager)
- **[Templates](./templates)** - Use templates for deployment configurations
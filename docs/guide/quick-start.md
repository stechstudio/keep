# Quick Start

This guide gets you managing secrets with Keep in 5 minutes. We'll assume you've already run `keep init` and set up your first vault (see [Configuration](./configuration) if you haven't).

## Setting Your First Secret

```bash
# Run interactively - you'll be prompted for key, value, and env
keep set

# Or specify everything directly
keep set DB_PASSWORD "super-secret-password" --env=local
```

## Viewing Your Secrets

```bash
# List all secrets (values are masked by default)
keep list --env=local

# Get a specific secret
keep get DB_PASSWORD --env=local

# Show the actual value (unmasked)
keep get DB_PASSWORD --env=local --unmask
```

## Export to .env File

```bash
# Export secrets to a .env file
keep export --env=local --output=.env
```

This creates a `.env` file with your secrets:
```env
DB_PASSWORD="super-secret-password"
```

## What's Next?

You're now managing secrets with Keep! Here's where to go next:

- **[Web UI](./web-ui/)** - Primary interface for secret management
- **[Interactive Shell](./shell)** - Exploratory operations and quick edits
- **[Vaults](./vaults)** - Add production vaults (AWS SSM, Secrets Manager)
- **[Deployment & Runtime](./deployment/)** - Production deployment strategies
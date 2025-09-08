# CLI Commands

Keep provides a comprehensive set of command-line tools for managing secrets. Each command is designed for both direct use and CI/CD automation.

## Command Guides

### [Creating & Viewing Secrets](./creating-viewing)
Set, get, list, and delete secrets across environments.

### [Cross-Environment Operations](./cross-environment) 
Copy secrets between stages, import from `.env` files, and compare environments.

### [Exporting to .env](./exporting-to-env)
Quick reference for export commands - see [Deployment & Runtime](/guide/deployment/) for comprehensive deployment strategies.

## Advanced Deployment

For production deployments, runtime injection, and template management, see the dedicated **[Deployment & Runtime](/guide/deployment/)** section which covers:

- **Runtime Secrets Injection** - Execute processes with injected secrets (no files)
- **Template Management** - Define and validate secret requirements
- **Export Strategies** - Choose the right approach for your use case

## Core Concepts

**Stages** organize secrets by environment:
- `local` - Local development environment
- `staging` - Pre-production testing
- `production` - Live environment

**Vaults** provide the storage backend:
- **AWS SSM** - Parameter Store for simple key-value storage
- **AWS Secrets Manager** - Advanced features with rotation support

**Context syntax** for cross-vault operations:
```bash
# Default vault
keep list --stage=production

# Explicit vault
keep list --stage=production --vault=ssm

# Vault:stage syntax
keep copy DB_PASSWORD --from=secretsmanager:local --to=ssm:production
```

## Common Workflows

**Local → Production:**
1. Set secrets in local environment
2. Test with exported `.env` files
3. Copy to staging for testing
4. Promote to production

**Team Collaboration:**
- Share vault configurations via `.keep/` directory
- Use consistent naming conventions
- Control production access with IAM policies

## Getting Help

```bash
keep [command] --help
```
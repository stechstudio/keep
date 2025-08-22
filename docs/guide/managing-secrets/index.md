# Managing Secrets

This section provides comprehensive guidance for working with secrets in Keep. Whether you're setting your first secret or managing complex multi-environment deployments, these guides will help you master Keep's secret management capabilities.

## What You'll Learn

### [Creating & Viewing Secrets](./creating-viewing)
Learn how to create, retrieve, and list secrets with all available options. Includes detailed command reference tables for `keep set`, `keep get`, and `keep list`.

### [Cross-Environment Operations](./cross-environment) 
Master copying secrets between stages, bulk operations, and importing from existing `.env` files. Covers `keep copy`, `keep import`, and promotion workflows.

### [Export & Deployment](./export-deployment)
Generate configuration files for deployment using exports and templates. Detailed coverage of `keep export`, `keep merge`, and CI/CD integration patterns.

## Core Concepts

### Stages
Keep organizes secrets by stage (environment):
- `development` - Local development work
- `staging` - Pre-production testing
- `production` - Live production environment

### Vaults
Secrets are stored in vaults (storage backends):
- **Local** - File-based storage for development
- **AWS SSM** - Parameter Store for production
- **AWS Secrets Manager** - Premium secret management with rotation

### Contexts
Many commands accept vault and stage combinations:
```bash
# Using default vault
keep list --stage=production

# Specifying vault explicitly  
keep list --stage=production --vault=aws-ssm

# Using vault:stage syntax
keep copy DB_PASSWORD --from=local:development --to=aws-ssm:production
```

## Common Workflows

### Development Workflow
1. **Set secrets** in development stage
2. **Test locally** with exported .env files
3. **Copy to staging** for integration testing
4. **Promote to production** when ready

### Team Collaboration
1. **Share vault configurations** via Keep config files
2. **Use consistent naming** across team members  
3. **Document secret purposes** and access patterns
4. **Implement approval processes** for production changes

### Deployment Integration
1. **Export secrets** during build/deploy
2. **Use templates** for environment-specific configuration
3. **Integrate with CI/CD** for automated deployment
4. **Verify secrets** before deployment

## Getting Help

Each command includes detailed help:
```bash
keep set --help
keep get --help
keep list --help
```

The following pages provide comprehensive command references with all options, arguments, and examples for effective secret management.
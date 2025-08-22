# Examples

This section provides practical examples of using Keep in real-world scenarios. These examples will help you understand how to integrate Keep into your workflow and get the most out of its features.

## Available Examples

### [Laravel Integration](./laravel)
Learn how to integrate Keep with your Laravel applications, including:
- Service provider setup
- Helper function usage  
- Caching strategies
- Development workflow

### [CI/CD Workflows](./ci-cd)
Examples of using Keep in continuous integration and deployment:
- GitHub Actions integration
- Automated secret deployment
- Environment promotion strategies
- Security best practices

### [Multi-Environment Setup](./multi-environment)
Best practices for organizing secrets across multiple environments:
- Stage organization strategies
- Vault selection guidelines
- Secret promotion workflows
- Team access patterns

### [AWS Setup](./aws-setup)
Complete guide to setting up Keep with AWS services:
- IAM roles and permissions
- SSM Parameter Store configuration
- Secrets Manager setup
- Cross-account access patterns

## Common Workflows

### Development to Production Flow
```bash
# 1. Set up local development
keep configure
keep vault:add local myapp
keep set myapp:development API_KEY "dev-key-123"

# 2. Set up staging environment  
keep vault:add aws-ssm staging-vault
keep copy myapp:development staging-vault:staging API_KEY

# 3. Promote to production
keep vault:add aws-secrets prod-vault  
keep copy staging-vault:staging prod-vault:production API_KEY
```

### Template-Based Configuration
```bash
# Create template file
echo "API_KEY={myapp:API_KEY}" > app.env.template

# Generate configuration
keep template:validate app.env.template myapp:production
keep template:merge app.env.template myapp:production > app.env
```

Each example includes complete code samples, configuration files, and step-by-step instructions to help you implement similar patterns in your own projects.
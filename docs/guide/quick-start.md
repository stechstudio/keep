# Quick Start

This guide will get you up and running with Keep in just a few minutes. We'll walk through the basic workflow of setting up a project, managing secrets, and generating configuration files.

## Step 1: Initialize Your Project

```bash
# Navigate to your project directory
cd my-laravel-app

# Initialize Keep configuration
keep configure
```

When prompted, provide:
- **Project name**: `My Laravel App`
- **Namespace**: `myapp` 
- **Stages**: `development,staging,production`
- **Default vault**: `local`

## Step 2: Add a Vault

Add a local vault for development:

```bash
keep vault:add local myapp
```

This creates a local file-based vault perfect for development work.

## Step 3: Set Your First Secret

```bash
keep set myapp:development DB_PASSWORD "super-secret-password"
keep set myapp:development API_KEY "dev-api-key-123"
keep set myapp:development MAIL_PASSWORD "mail-secret"
```

## Step 4: View Your Secrets

List all secrets in the development stage:

```bash
keep list myapp:development
```

Get a specific secret:

```bash
keep get myapp:development API_KEY
```

## Step 5: Export to .env File

Generate a `.env` file from your secrets:

```bash
keep export myapp:development --format=env > .env
```

This creates a `.env` file with all your development secrets:

```env
DB_PASSWORD="super-secret-password"
API_KEY="dev-api-key-123"  
MAIL_PASSWORD="mail-secret"
```

## Step 6: Promote to Staging

Copy secrets to staging environment:

```bash
keep copy myapp:development myapp:staging DB_PASSWORD
keep copy myapp:development myapp:staging API_KEY

# Or copy all secrets at once
keep copy myapp:development myapp:staging
```

Update staging-specific values:

```bash
keep set myapp:staging API_KEY "staging-api-key-456"
```

## Step 7: Working with Templates

Create a template file `app.env.template`:

```env
# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD={myapp:DB_PASSWORD}

# API Configuration  
API_KEY={myapp:API_KEY}
API_URL=https://api.example.com

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=myapp@gmail.com
MAIL_PASSWORD={myapp:MAIL_PASSWORD}
```

Generate configuration from template:

```bash
keep template:merge app.env.template myapp:development > .env
```

## Step 8: Validate Your Setup

Verify everything is working:

```bash
# Check vault configuration
keep vault:list

# Validate a template
keep template:validate app.env.template myapp:development

# Show project information
keep info
```

## What's Next?

You now have a basic Keep setup! Here are some next steps:

- **Add AWS Integration**: Set up [AWS SSM](./vaults/aws-ssm) or [Secrets Manager](./vaults/aws-secrets-manager) for production
- **Team Collaboration**: Share vault configurations with your team
- **CI/CD Integration**: Automate secret deployment in your [CI/CD pipeline](../examples/ci-cd)
- **Laravel Integration**: Set up the [Laravel service provider](../examples/laravel) for seamless integration

## Common Commands Reference

```bash
# Set a secret
keep set [context] [key] [value]

# Get a secret
keep get [context] [key]

# List secrets
keep list [context]

# Copy secrets between contexts
keep copy [source] [destination] [key]

# Export secrets
keep export [context] --format=[env|json|yaml]

# Delete a secret
keep delete [context] [key]
```

Remember: A context is always in the format `vault:stage` (e.g., `myapp:development`).
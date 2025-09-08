# Exporting to .env

Keep provides powerful export capabilities for generating configuration files from your secrets. This page provides a quick reference - for detailed deployment strategies, see the [Deployment & Runtime](/guide/deployment/) section.

## Quick Reference

### Export Command

```bash
# Export to .env file
keep export --stage=production --file=.env

# Export with template
keep export --template=env/prod.env --stage=production --file=.env

# Export as JSON
keep export --stage=production --format=json --file=config.json
```

### Run Command (Runtime Injection)

```bash
# Run process with injected secrets
keep run --vault=aws-ssm --stage=production -- npm start

# Use template for specific secrets
keep run --vault=aws-ssm --stage=production --template -- npm start
```

## Deployment Strategies

Keep offers three main approaches for getting secrets into your applications:

### 1. Runtime Injection (Recommended)
**Most secure** - Inject secrets directly into processes without writing to disk.

```bash
keep run --vault=aws-ssm --stage=production -- npm start
```

[Learn more about Runtime Injection →](/guide/deployment/runtime-injection)

### 2. Template Management
**Most flexible** - Define which secrets your apps need using templates.

```bash
# Create template
keep template:add production.env --stage=production

# Use template
keep export --template=production.env --stage=production --file=.env
```

[Learn more about Templates →](/guide/deployment/templates)

### 3. File Export
**Most compatible** - Generate .env files for legacy applications.

```bash
keep export --stage=production --file=.env
```

[Learn more about File Export →](/guide/deployment/exporting)

## Complete Documentation

For comprehensive guides on deployment strategies, template management, and security best practices, see the **[Deployment & Runtime](/guide/deployment/)** section.

### Key Topics Covered:
- [Runtime Secrets Injection](/guide/deployment/runtime-injection) - Execute processes with injected secrets
- [Managing Templates](/guide/deployment/templates) - Create and manage configuration templates
- [Exporting to Files](/guide/deployment/exporting) - Generate configuration files
- Security comparisons and best practices
- Framework-specific examples (Laravel, Node.js, Python, Docker)
- CI/CD integration patterns
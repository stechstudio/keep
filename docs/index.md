---
layout: home

hero:
  name: "Keep"
  text: "Secret Management Made Simple"
  tagline: Collaborative, secure management of secrets across applications, environments, and teams.
  actions:
    - theme: brand
      text: Get Started
      link: /guide/
    - theme: alt
      text: View on GitHub
      link: https://github.com/stechstudio/keep

features:
  - title: Multi-Vault Support
    details: Support for local files, AWS SSM, and AWS Secrets Manager with consistent interface across all providers.
    
  - title: Stage Management  
    details: Organize secrets by environment stages (development, staging, production) with easy promotion between stages.
    
  - title: Template System
    details: Generate .env files and configuration from templates with placeholder replacement and validation.
    
  - title: Laravel Integration
    details: Seamless integration with Laravel applications including helper functions and service provider.
    
  - title: CLI First
    details: Powerful command-line interface for all operations with support for CI/CD workflows and automation.
    
  - title: Security Focused
    details: Encrypted local storage, secure AWS integration, and careful handling of sensitive data throughout.
---

## Quick Example

```bash
# Configure your project
keep configure

# Add a vault
keep vault:add local myapp

# Set a secret
keep set myapp:development DB_PASSWORD "super-secret"

# Export to .env file
keep export myapp:development --format=env > .env
```

## Why Keep?

Keep solves the challenge of managing secrets across multiple environments and team members. Instead of sharing `.env` files through Slack or email, Keep provides:

- **Centralized Management**: All secrets in one place with proper access control
- **Environment Isolation**: Separate secrets by stage while maintaining consistency
- **Team Collaboration**: Share secrets securely without exposing them in chat or email
- **Integration Ready**: Works seamlessly with your existing Laravel applications
- **Cloud Native**: Built-in support for AWS services with local development options

Get started by following our [installation guide](/guide/installation) or explore the [CLI reference](/reference/) to see all available commands.
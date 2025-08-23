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
    details: AWS SSM and Secrets Manager, with more providers coming soon.
    
  - title: Stage Management  
    details: Organize secrets by environment with seamless promotion workflows.
    
  - title: Template System
    details: Generate configuration files from templates with smart placeholder replacement.
    
  - title: CLI First
    details: Powerful command-line interface built for CI/CD automation.
    
  - title: Laravel Integration
    details: Native Laravel support with helper functions and service provider.
    
  - title: Security Focused
    details: Encrypted storage, secure AWS integration, and masked output by default.
---

## Quick Example

```bash
# Configure your project
keep configure

# Add a vault
keep vault:add

# Set secrets
keep set DB_PASSWORD "super-secret" --stage=production

# List secrets
keep list --stage=staging

# Compare environments
keep diff --stage=staging,production

# Export to .env
keep export --stage=production --output=.env

# Use templates
keep merge .env.template --stage=production --output=.env
```

Get started with our [installation guide](/guide/installation) or see all [CLI commands](/guide/cli-reference).
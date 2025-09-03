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
    
  - title: Interactive Shell
    details: Full REPL with tab completion, context persistence, and instant secret access.

  - title: User-Friendly Web UI
    details: Visual secret management with diff matrix, import wizard, and real-time search.
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
keep export --template=.env.template --stage=production --output=.env
```

## Interactive Shell

Launch the Keep shell for faster secret management with tab completion:

```bash
# Start the interactive shell
keep shell

# In the shell, use shortcuts and tab completion
>>> stage production         # Switch to production stage
>>> get DB_<TAB>            # Tab completes secret names
>>> set NEW_SECRET "value"  # Set secrets instantly
>>> diff staging production # Compare environments
>>> exit                    # Exit when done
```

## Web UI

Launch a modern browser-based interface for visual secret management:

```bash
# Start the web server
keep server

# The UI provides:
# - Visual diff matrix comparing environments
# - Drag-and-drop import with conflict resolution
# - Real-time search and filtering
# - Export in multiple formats with preview
# - Settings management for vaults and stages
```

Get started with our [installation guide](/guide/installation), explore the [interactive shell](/guide/shell), try the [Web UI](/WEB_UI), or see all [CLI commands](/guide/reference/cli-reference).
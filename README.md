# Keep

[![Latest Version on Packagist](https://img.shields.io/packagist/v/stechstudio/keep.svg?style=flat-square)](https://packagist.org/packages/stechstudio/keep)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Tests](https://img.shields.io/github/actions/workflow/status/stechstudio/keep/tests.yml?branch=main&style=flat-square)](https://github.com/stechstudio/keep/actions/workflows/tests.yml)

**Keep** is your toolkit for secure, collaborative management of application secrets across environments and teams.

## Key Features

- **🔐 Multi-Vault Support** - AWS SSM Parameter Store and AWS Secrets Manager
- **🖥️ Web UI** - Modern browser-based interface for visual secret management
- **🚀 Interactive Shell** - Context-aware shell with tab completion for rapid secret management
- **🌍 Environment Isolation** - Separate secrets by stage (local, staging, production)
- **📝 Template Management** - Create, validate, and process templates with placeholders
- **🔄 Bulk Operations** - Import, export, copy, and diff secrets across environments
- **🤝 Team Collaboration** - Share secret management with proper access controls
- **⚙️ CI/CD Ready** - Export secrets for deployment pipelines

## Quick Example

```bash
# Install
composer require stechstudio/keep

# Configure
./vendor/bin/keep configure

# Interactive shell - the fastest way to work
./vendor/bin/keep shell

# Set a secret
./vendor/bin/keep set DB_PASSWORD "secret" --stage=production

# Export to .env
./vendor/bin/keep export --stage=production --file=.env

# Create template from existing secrets
./vendor/bin/keep template:add .env.template --stage=production

# Use template with placeholders
./vendor/bin/keep export --stage=production --template=.env.template --file=.env
```

## Interactive Shell

The Keep shell provides a context-aware environment for managing secrets:

```bash
$ ./vendor/bin/keep shell
Welcome to Keep Shell v1.0.0

ssm:local> use production
Switched to: ssm:production

ssm:production> set API_KEY
Value: ********

ssm:production> copy API_KEY staging
✓ Copied API_KEY to staging

ssm:production> diff staging production
│ Key     │ staging │ production │ Status │
├─────────┼─────────┼────────────┼────────┤
│ API_KEY │ abc...  │ abc...     │ ✓      │
```

## Web UI

Keep includes a modern web interface for visual secret management:

```bash
# Start the web server
./vendor/bin/keep server

# Custom port (default: 4000)
./vendor/bin/keep server --port=8080

# Don't auto-open browser
./vendor/bin/keep server --no-browser
```

The Web UI provides:
- **Visual secret management** with search and filtering
- **Diff matrix view** comparing secrets across stages/vaults
- **Export functionality** with live preview
- **Import wizard** for .env files with conflict resolution
- **Settings management** for vaults and stages
- **Real-time validation** and error handling

## Documentation

📚 **Full documentation available at [https://stechstudio.github.io/keep/](https://stechstudio.github.io/keep/)**

- [Installation & Configuration](https://stechstudio.github.io/keep/guide/installation)
- [Managing Secrets](https://stechstudio.github.io/keep/guide/managing-secrets/)
- [Interactive Shell Guide](https://stechstudio.github.io/keep/guide/shell)
- [AWS Authentication](https://stechstudio.github.io/keep/guide/reference/aws-authentication)
- [CLI Reference](https://stechstudio.github.io/keep/guide/reference/cli-reference)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
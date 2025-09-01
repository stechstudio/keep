# Keep

[![Latest Version on Packagist](https://img.shields.io/packagist/v/stechstudio/keep.svg?style=flat-square)](https://packagist.org/packages/stechstudio/keep)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Tests](https://img.shields.io/github/actions/workflow/status/stechstudio/keep/tests.yml?branch=main&style=flat-square)](https://github.com/stechstudio/keep/actions/workflows/tests.yml)

**Keep** is your toolkit for secure, collaborative management of application secrets across environments and teams.

## Key Features

- **🔐 Multi-Vault Support** - AWS SSM Parameter Store and AWS Secrets Manager
- **🚀 Interactive Shell** - Context-aware shell with tab completion for rapid secret management
- **🌍 Environment Isolation** - Separate secrets by stage (local, staging, production)
- **📝 Template System** - Merge secrets into templates while preserving structure
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

## Documentation

📚 **Full documentation available at [https://stechstudio.github.io/keep/](https://stechstudio.github.io/keep/)**

- [Installation & Configuration](https://stechstudio.github.io/keep/guide/installation)
- [Managing Secrets](https://stechstudio.github.io/keep/guide/managing-secrets/)
- [Interactive Shell Guide](https://stechstudio.github.io/keep/guide/shell)
- [AWS Authentication](https://stechstudio.github.io/keep/guide/reference/aws-authentication)
- [CLI Reference](https://stechstudio.github.io/keep/guide/reference/cli-reference)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
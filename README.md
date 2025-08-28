# Keep

[![Latest Version on Packagist](https://img.shields.io/packagist/v/stechstudio/keep.svg?style=flat-square)](https://packagist.org/packages/stechstudio/keep)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Tests](https://img.shields.io/github/actions/workflow/status/stechstudio/keep/tests.yml?branch=main&style=flat-square)](https://github.com/stechstudio/keep/actions/workflows/tests.yml)


**Keep** is your toolkit for collaborative, secure management of secrets across applications, environments, and teams.

**Key Features:**
- **CLI Commands** - Manage individual secrets, import/export in bulk, view history and diffs, all via artisan commands
- **Multi-Vault Support** - Driver-based system, currently supporting AWS SSM Parameter Store and AWS Secrets Manager
- **Environment Isolation** - Separate secrets by environment (local, staging, production) with access controls
- **Unified Export System** - Direct export, template processing, and encrypted caching all in one command
- **Template System** - Replace placeholders in templates with vault secrets while preserving formatting
- **Team Collaboration** - Share secret management across team members with proper access controls
- **CI/CD Integration** - Export secrets for deployment pipelines and automated workflows

The package provides a secure, organized way to manage application secrets without storing them in version control or sharing them insecurely.

## Quick Start

### Install and configure Keep

Install the package via composer:

```bash
composer require stechstudio/keep
```

This will install a command in your `vendor/bin` directory called `keep`. Run `keep configure` to configure Keep and your first vault.

```bash
./vendor/bin/keep configure
```

You should now have Keep configured with a default vault. Run `keep verify` to check your setup and ensure you have necessary permissions.

```bash
./vendor/bin/keep verify
```

### Manage secrets

You can add secrets using `keep set`:

```bash
# You will be prompted for the stage and secret value
./vendor/bin/keep set DB_PASSWORD

# Or specify the stage and value directly
./vendor/bin/keep set DB_PASSWORD --stage=production --value="supersecretpassword"
```

This will store the `DB_PASSWORD` secret in AWS SSM under the path `/[namespace]/production/DB_PASSWORD`.

Check that the secret was added:

```bash
# Retrieve a single secret
./vendor/bin/keep get DB_PASSWORD --stage=production

# List all secrets for production
./vendor/bin/keep list --stage=production
```

### Using secrets in your application

#### Direct Export - Generate complete `.env` file from secrets

If all your environment variables are managed via Keep, export them directly to a .env file:

```bash
# Export all secrets from all vaults
./vendor/bin/keep export --stage=production --file=.env

# Export from specific vaults only
./vendor/bin/keep export --stage=production --vault=ssm,secrets --file=.env

# Export as JSON format
./vendor/bin/keep export --stage=production --format=json --file=config.json
```

#### Template Mode - Merge secrets into a template file

Use a template file with placeholders for sensitive values:

Example `.env.template`:

```env
# Application Config
APP_NAME=MyApp
APP_ENV=production

# Database - sensitive values from vaults
DB_HOST={aws-ssm:database/host}
DB_PORT=3306  # Static value
DB_PASSWORD={aws-secrets:db-password}

# API Keys
API_KEY={vault1:api/key}
```

Then process the template:

```bash
# Replace placeholders with actual secrets
./vendor/bin/keep export --stage=production --template=.env.template --file=.env

# Include ALL vault secrets (template + additional)
./vendor/bin/keep export --stage=production --template=.env.template --all --file=.env

# Handle missing secrets gracefully
./vendor/bin/keep export --stage=production --template=.env.template --missing=blank --file=.env
```

#### Encrypted Cache - For Laravel Integration

Export secrets to an encrypted cache for use with Laravel's config caching:

```bash
./vendor/bin/keep export --stage=production --cache
```

This creates an encrypted cache file in `.keep/cache/` and adds the decryption key to your `.env` file.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

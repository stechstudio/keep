<img width="1492" height="713" alt="keep" src="https://github.com/user-attachments/assets/3e920224-0093-4fab-8a6e-5f548ad5e2a6" />

# Laravel Keep

[![Latest Version on Packagist](https://img.shields.io/packagist/v/stechstudio/laravel-keep.svg?style=flat-square)](https://packagist.org/packages/stechstudio/laravel-keep)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)

**Laravel Keep** is a toolkit for managing application secrets across environments and teams.

**Key Features:**
- **CLI Commands** - Set, get, list, import, export, and merge secrets via artisan commands
- **Multi-Vault Support** - Driver-based system (AWS SSM Parameter Store, extensible for other providers)
- **Environment Isolation** - Separate secrets by environment (local, staging, production) with access controls
- **Template System** - Merge secrets into `.env` files using template placeholders
- **Team Collaboration** - Share secret management across team members with proper access controls
- **CI/CD Integration** - Export secrets for deployment pipelines and automated workflows

The package provides a secure, organized way to manage Laravel application secrets without storing them in version control or sharing them insecurely.

## Installation

You can install the package via composer:

```bash
composer require stechstudio/laravel-keep
```

## Quick Example

Let's say you have three environments (local, staging, production) and you want to store secrets in AWS SSM with the default KMS encryption key.

### Setup

1. Install the package via composer (as shown above).
2. Ensure you have AWS credentials configured in your environment, with permissions to access SSM Parameter Store (see docs for full example).

### Manage secrets

You can add secrets using the artisan command:

```bash
# You will be prompted to enter the secret value
php artisan keep:set DB_PASSWORD --env=production
```

This will store the `DB_PASSWORD` secret in AWS SSM under the path `/[app-name-slug]/production/DB_PASSWORD`.

Check that the secret was added:

```bash
# Retrieve a single secret
php artisan keep:get DB_PASSWORD --env=production

# List all secrets for the production environment
php artisan keep:list --env=production
```

### Using secrets in your application

If 100% of your .env variables are managed via Keep, you can export them all to a .env file as part of your deployment process:

```bash
php artisan keep:export --env=production --output=.env
```

You can also have a template env file with some non-sensitive values and merge the secrets into it:

Example `.env.base` template:

```env
APP_NAME=MyApp
# ...
DB_DATABASE=myapp_db
DB_PASSWORD={aws-ssm:DB_PASSWORD} # or just {aws-ssm} since the key matches the variable name
```

Then run the merge command:

```bash
php artisan keep:merge .env.base .env --env=production
```

You will not have a `.env` file with all the values from the template and the secrets filled in.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

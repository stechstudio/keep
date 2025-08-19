<img width="1492" height="713" alt="keep" src="https://github.com/user-attachments/assets/17b4b25e-df55-459e-b835-5377cb1834ee" />

# Laravel Keep

[![Latest Version on Packagist](https://img.shields.io/packagist/v/stechstudio/laravel-keep.svg?style=flat-square)](https://packagist.org/packages/stechstudio/laravel-keep)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Tests](https://img.shields.io/github/actions/workflow/status/stechstudio/laravel-keep/tests.yml?branch=main&style=flat-square)](https://github.com/stechstudio/laravel-keep/actions/workflows/tests.yml)


**Laravel Keep** is a toolkit for managing application secrets across environments and teams.

**Key Features:**
- **CLI Commands** - Manage individual secrets, import/export in bulk, view history and diffs, all via artisan commands
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

Let's say you have three environments (local, staging, production) and you want to store secrets in AWS SSM with the default KMS encryption key, in the `us-east-1` region.

### Setup

1. Install the package via composer (as shown above).
2. Ensure you have AWS credentials configured in your environment, with permissions to access SSM Parameter Store (see docs for full example).
3. Run `php artisan keep:verify` to check your setup, verify your vault configuration, and ensure you have necessary permissions.

### Manage secrets

You can add secrets using the artisan command:

```bash
# You will be prompted for the stage and secret value
php artisan keep:set DB_PASSWORD

# Or specify the stage and value directly
php artisan keep:set DB_PASSWORD --stage=production --value="supersecretpassword"
```

This will store the `DB_PASSWORD` secret in AWS SSM under the path `/[app-name-slug]/production/DB_PASSWORD`.

Check that the secret was added:

```bash
# Retrieve a single secret
php artisan keep:get DB_PASSWORD --stage=production

# List all secrets for production
php artisan keep:list --stage=production
```

### Using secrets in your application

#### Generate complete `.env` file from secrets

If 100% of your .env variables are managed via Keep, you can export them all to a .env file as part of your deployment process:

```bash
php artisan keep:export --stage=production --output=.env
```

#### Merge secrets into a base template `.env` file

You can also have a template env file with some non-sensitive values and merge the secrets into it:

Example `.env.base` template:

```env
APP_NAME=MyApp
# ...
DB_DATABASE=myapp_db
DB_PASSWORD={ssm:DB_PASSWORD} # or just {ssm} since the key matches the variable name
```

Then run the merge command:

```bash
php artisan keep:merge --template=.env.base --output=.env --stage=production
```

You will now have a `.env` file with all the values from the template and the secrets filled in.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

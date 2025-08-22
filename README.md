# Keep

[![Latest Version on Packagist](https://img.shields.io/packagist/v/stechstudio/keep.svg?style=flat-square)](https://packagist.org/packages/stechstudio/keep)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Tests](https://img.shields.io/github/actions/workflow/status/stechstudio/keep/tests.yml?branch=main&style=flat-square)](https://github.com/stechstudio/keep/actions/workflows/tests.yml)


**Keep** is your toolkit for collaborative, secure management of secrets across applications, environments, and teams.

**Key Features:**
- **CLI Commands** - Manage individual secrets, import/export in bulk, view history and diffs, all via artisan commands
- **Multi-Vault Support** - Driver-based system, currently supporting AWS SSM Parameter Store and AWS Secrets Manager
- **Environment Isolation** - Separate secrets by environment (local, staging, production) with access controls
- **Template System** - Merge secrets into `.env` files using template placeholders
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

#### Generate complete `.env` file from secrets

If 100% of your .env variables are managed via Keep, you can export them all to a .env file as part of your deployment process:

```bash
./vendor/bin/keep export --stage=production --output=.env
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
./vendor/bin/keep merge --template=.env.base --output=.env --stage=production
```

You will now have a `.env` file with all the values from the template and the secrets filled in.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

# Installation

Keep can be installed globally as a standalone CLI tool or as a Composer dependency in your Laravel project.

## Project Installation

It's usually best to install Keep in your local project:

```bash
composer require --dev stechstudio/keep
```

Then run Keep commands using:

```bash
./vendor/bin/keep [command]
```

You might appreciate having an alias in your shell profile to make it easier:

```bash
alias keep="./vendor/bin/keep"
```

## Global Installation (Optional)

If you don't plan to use any framework integration, you can install Keep globally:

```bash
composer global require stechstudio/keep
```

Make sure your global Composer vendor bin directory is in your `$PATH`:

```bash
export PATH="$PATH:$HOME/.composer/vendor/bin"
````

Then run Keep commands using:

```bash
keep [command]
```

## Laravel Integration (Optional)

If you're using Keep with a Laravel application, you can publish the configuration and set up the service provider:

```bash
# Publish configuration
php artisan vendor:publish --tag=keep-config
```

## Verify Installation

Verify Keep is installed correctly:

```bash
keep --version
```

You should see the Keep version number displayed.

## Next Steps

Now that Keep is installed, let's [configure your first project](./configuration).

## Requirements

- PHP 8.3 or higher
- Composer 2.0 or higher
- For AWS integrations: AWS CLI configured with appropriate credentials
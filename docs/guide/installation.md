# Installation

Keep can be installed globally as a standalone CLI tool or as a Composer dependency in your Laravel project.

## Global Installation (Recommended)

Install Keep globally using Composer:

```bash
composer global require stechstudio/laravel-keep
```

Make sure your global Composer vendor bin directory is in your `$PATH`:

```bash
export PATH="$PATH:$HOME/.composer/vendor/bin"
```

Add this line to your shell profile (`.bashrc`, `.zshrc`, etc.) to make it permanent.

## Project-Specific Installation

If you prefer to install Keep per project:

```bash
composer require --dev stechstudio/laravel-keep
```

Then run Keep commands using:

```bash
./vendor/bin/keep [command]
```

## Laravel Integration (Optional)

If you're using Keep with a Laravel application, you can publish the configuration and set up the service provider:

```bash
# Publish configuration
php artisan vendor:publish --provider="STS\Keep\KeepServiceProvider"

# Configure Laravel integration
keep configure --laravel
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
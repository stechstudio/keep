# Installation

## Requirements

- PHP 8.3 or higher
- Composer 2.0 or higher
- AWS credentials configured (for AWS vaults) - see [AWS Authentication](/guide/aws-authentication)

## Installation Options

### PHP Applications (Recommended)

For PHP projects, install Keep as a dependency to ensure it's available across all environments:

```bash
composer require --dev stechstudio/keep
```

Run commands using:

```bash
./vendor/bin/keep [command]
```

This approach ensures Keep is automatically available on other developer machines, staging, and production servers when dependencies are installed.

**Tip:** Add an alias to your shell profile:

```bash
alias keep="./vendor/bin/keep"
```

### Non-PHP Applications or Global Use

For Node.js, Python, Ruby, or other non-PHP applications, install Keep globally:

```bash
composer global require stechstudio/keep
```

Add Composer's global bin to your `$PATH`:

```bash
export PATH="$PATH:$HOME/.composer/vendor/bin"
```

**Note:** Global installation requires manual setup on each machine and environment where Keep is needed.

<!-- Laravel Integration deferred to future release
## Laravel Integration

For Laravel applications, publish the configuration:

```bash
php artisan vendor:publish --tag=keep-config
```
-->

## Verify Installation

```bash
keep --version
```

## Next Steps

[Configure your first project](./configuration).
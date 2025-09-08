# Installation

## Requirements

- PHP 8.3 or higher
- Composer 2.0 or higher
- AWS credentials configured (for AWS vaults) - see [AWS Authentication](/guide/aws-authentication)

## Project Installation (Recommended)

Install Keep as a development dependency:

```bash
composer require --dev stechstudio/keep
```

Run commands using:

```bash
./vendor/bin/keep [command]
```

**Tip:** Add an alias to your shell profile:

```bash
alias keep="./vendor/bin/keep"
```

## Global Installation

For standalone use across projects:

```bash
composer global require stechstudio/keep
```

Add Composer's global bin to your `$PATH`:

```bash
export PATH="$PATH:$HOME/.composer/vendor/bin"
```

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
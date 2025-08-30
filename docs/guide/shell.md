# Interactive Shell

Keep provides a powerful interactive shell (REPL) with tab completion and persistent context for efficient secret management. The shell is powered by [PsySH](https://psysh.org/), a runtime developer console for PHP.

## Starting the Shell

Launch the interactive shell with the `shell` command:

```bash
keep shell
```

You can also start with a specific vault and stage:

```bash
keep shell --vault=myapp --stage=production
```

## Features

### Tab Completion

The shell provides intelligent tab completion for:

- **Commands**: Type partial commands and press TAB to complete
- **Secret names**: When using `get`, `set`, `delete`, and other secret commands
- **Vault names**: When switching vaults with the `vault` command
- **Stage names**: When switching stages with the `stage` command

```bash
>>> get DB_<TAB>
DB_HOST     DB_PASSWORD     DB_PORT     DB_USERNAME

>>> stage <TAB>
development     staging     production

>>> vault <TAB>
myapp     shared-config     infrastructure
```

### Persistent Context

The shell maintains your current vault and stage context throughout your session:

```bash
>>> context
Current vault: myapp
Current stage: staging

>>> stage production
Switched to stage: production

>>> context
Current vault: myapp
Current stage: production
```

### Command Shortcuts

Use short aliases for common commands:

| Full Command | Shortcut | Description |
|-------------|----------|-------------|
| `get KEY` | `g KEY` | Get a secret value |
| `set KEY VALUE` | `s KEY VALUE` | Set a secret |
| `delete KEY` | `d KEY` | Delete a secret |
| `show` | `l`, `ls` | Show all secrets in current context |
| `stage STAGE` | - | Switch to a different stage |
| `vault NAME` | - | Switch to a different vault |

## Usage Examples

### Quick Secret Management

```bash
>>> stage production
Switched to stage: production

>>> set API_KEY "sk-1234567890"
✓ Set API_KEY in myapp:production

>>> get API_KEY
sk-1234567890

>>> show
API_KEY
DB_HOST
DB_PASSWORD
DB_USERNAME
```

### Comparing Environments

```bash
>>> diff staging production
Comparing staging vs production

Only in staging:
  DEBUG_MODE = true

Only in production:
  SENTRY_DSN = https://...

Different values:
  API_ENDPOINT:
    staging:    https://api-staging.example.com
    production: https://api.example.com
```

### Bulk Operations

The shell supports all Keep commands, including bulk operations:

```bash
>>> copy --key="DB_*" --stage=staging,production
Copying from staging to production...
✓ Copied DB_HOST
✓ Copied DB_PASSWORD
✓ Copied DB_USERNAME
✓ Copied DB_PORT
Copied 4 secrets
```

### Stage Promotion

```bash
>>> stage:add testing --copy-from=development
✓ Created stage 'testing' in vault 'myapp'
✓ Copied 15 secrets from development to testing

>>> stage testing
Switched to stage: testing

>>> show
# Shows all copied secrets
```

## Shell Commands

### Context Management

- **`stage <name>`** - Switch to a different stage
- **`use <vault:stage>`** - Switch both vault and stage at once
- **`vault <name>`** - Switch to a different vault
- **`context`** - Show current vault and stage

### Secret Operations

All standard Keep commands work in the shell:

- **`get <key>`** - Retrieve a secret value
- **`set <key> <value>`** - Set a secret
- **`delete <key>`** - Delete a secret
- **`show`** - Show all secrets in current context
- **`search <pattern>`** - Search for secrets by pattern
- **`history <key>`** - View secret history
- **`copy`** - Copy secrets between stages

### Vault Management

- **`vault:list`** - List all configured vaults
- **`vault:info`** - Show current vault details
- **`stage:list`** - List all stages in current vault
- **`stage:add`** - Create a new stage

### Comparison and Export

- **`diff <stage1> <stage2>`** - Compare secrets between stages
- **`export`** - Export secrets to .env format
- **`verify`** - Verify template placeholders

### Exiting the Shell

Exit the shell with:

```bash
>>> exit
Goodbye!
```

Or use the keyboard shortcut `Ctrl+D`.

## Requirements

The interactive shell requires PsySH. If not already installed, add it to your project:

```bash
composer require psy/psysh
```

## Options Syntax

The shell uses a natural, dash-free syntax for options:

```bash
>>> show unmask              # Show secrets with actual values
>>> show json                # Output in JSON format
>>> show env unmask          # Export format with real values
>>> show only DB_*           # Filter secrets by pattern
>>> get NAME raw             # Get raw value without table
>>> diff staging prod unmask # Compare with unmasked values
```

Options are simply words after the command - no dashes or equals signs needed.

## Tips and Tricks

### 1. Quick Context Switching

Use the `stage` command to quickly switch between environments:

```bash
>>> stage staging
>>> set DEBUG true
>>> stage production
>>> set DEBUG false
```

### 2. Secret Name Patterns

Tab completion works with partial matches:

```bash
>>> get AWS<TAB>
AWS_ACCESS_KEY_ID     AWS_SECRET_ACCESS_KEY     AWS_REGION
```

### 3. Command History

Use arrow keys to navigate through command history, making it easy to repeat or modify previous commands.

### 4. Inline PHP

Since the shell is powered by PsySH, you can execute PHP code directly:

```bash
>>> $secrets = Keep::vault('myapp', 'production')->list();
>>> count($secrets)
42
```

### 5. Multi-line Input

For complex commands or values, the shell supports multi-line input:

```bash
>>> set PRIVATE_KEY "-----BEGIN PRIVATE KEY-----
... MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEA
... -----END PRIVATE KEY-----"
```

## Performance

The shell implements several optimizations:

- **Secret name caching**: Secret names are cached for 60 seconds to improve tab completion performance
- **Lazy loading**: Vaults are only loaded when accessed
- **Minimal overhead**: Direct command execution without shell spawning

## See Also

- [CLI Reference](/guide/reference/cli-reference) - Complete command documentation
- [Managing Secrets](/guide/managing-secrets/) - Secret management guide
- [Quick Start](/guide/quick-start) - Getting started with Keep
# Interactive Shell

Keep provides a powerful interactive shell with tab completion and persistent context for efficient secret management.

## Starting the Shell

Launch the interactive shell with the `shell` command:

```bash
keep shell
```

You can also start with a specific vault and stage:

```bash
keep shell --vault=ssm --stage=production
```

## Features

### Context-Aware Prompt

The shell prompt always displays your current vault and stage in the format `vault:stage>`, making it easy to see exactly where you're working:

```bash
ssm:development>     # Working in SSM vault, development stage
aws:production>      # Working in AWS vault, production stage
test:staging>        # Working in test vault, staging stage
```

### Tab Completion

The shell provides intelligent tab completion for:

- **Commands**: Type partial commands and press TAB to complete
- **Secret names**: When using `get`, `set`, `delete`, and other secret commands
- **Stage names**: When switching stages with the `stage` command
- **Vault names**: When switching vaults with the `vault` command

```bash
ssm:development> get<TAB>                 # Shows all available secrets
ssm:development> get DB_<TAB>             # Shows secrets starting with DB_
DB_HOST     DB_PASSWORD     DB_PORT     DB_USERNAME

ssm:development> stage <TAB>              # Shows all configured stages
development     staging     production

ssm:development> vault <TAB>              # Shows all configured vaults
ssm     secretsmanager     test
```

### Persistent Context

The shell maintains your current vault and stage context throughout your session:

```bash
ssm:staging> context
Current context: ssm:staging

ssm:staging> stage production
Switched to stage: production

ssm:production> context
Current context: ssm:production
```

### Command Shortcuts

Use short aliases for common commands:

| Full Command | Shortcut | Description |
|-------------|----------|-------------|
| `get KEY` | `g KEY` | Get a secret value |
| `set KEY VALUE` | `s KEY VALUE` | Set a secret |
| `delete KEY` | `d KEY` | Delete a secret |
| `show` | `ls` | Show all secrets in current context |
| `stage STAGE` | - | Switch to a different stage |
| `vault NAME` | - | Switch to a different vault |

## Usage Examples

### Quick Secret Management

```bash
ssm:staging> stage production
Switched to stage: production

ssm:production> set API_KEY "sk-1234567890"
Secret [/test-app/production/API_KEY] created in vault [ssm].

ssm:production> get API_KEY
┌─────────┬───────┬───────────────┬──────────┐
│ Key     │ Vault │ Value         │ Revision │
├─────────┼───────┼───────────────┼──────────┤
│ API_KEY │ ssm   │ sk-1********* │ 1        │
└─────────┴───────┴───────────────┴──────────┘

ssm:production> show
┌─────────────┬─────────────────┬──────────┐
│ Key         │ Value           │ Revision │
├─────────────┼─────────────────┼──────────┤
│ API_KEY     │ sk-1*********   │ 1        │
│ DB_HOST     │ prod**********  │ 1        │
│ DB_PASSWORD │ supe*********** │ 1        │
│ DB_USERNAME │ ****            │ 1        │
└─────────────┴─────────────────┴──────────┘
```

### Comparing Environments

```bash
ssm:development> diff staging production

Secret Comparison Matrix
┌──────────────┬──────────────────┬──────────────────┬─────────────┐
│ Key          │ staging          │ production       │ Status      │
├──────────────┼──────────────────┼──────────────────┼─────────────┤
│ API_ENDPOINT │ ✓ api-*********  │ ✓ api.********** │ ⚠ Different │
│ DB_HOST      │ ✓ loca*****      │ ✓ prod********** │ ⚠ Different │
│ DEBUG_MODE   │ ✓ ****           │ —                │ ⚠ Missing   │
│ SENTRY_DSN   │ —                │ ✓ http********** │ ⚠ Missing   │
│ APP_KEY      │ ✓ base********** │ ✓ base********** │ ✓ Identical │
└──────────────┴──────────────────┴──────────────────┴─────────────┘

ssm:development> diff staging production unmask  # Show actual values
```

### Bulk Operations

The shell supports all Keep commands with natural syntax. The copy command always uses your current context as the source:

```bash
# Current context: ssm:development
ssm:development> context
Current context: ssm:development

# Copy a single secret to another stage (same vault)
ssm:development> copy API_KEY production
Copying secret from ssm:development to ssm:production
✓ Copied API_KEY

# Copy to a different vault and stage
ssm:development> copy DB_PASSWORD aws-secrets:staging
Copying secret from ssm:development to aws-secrets:staging
✓ Copied DB_PASSWORD

# Copy with patterns (prompts for destination)
ssm:development> copy only DB_*
To (vault): ssm
To (stage): production
Copying secrets from ssm:development to ssm:production
✓ Copied DB_HOST
✓ Copied DB_PASSWORD
✓ Copied DB_USERNAME
✓ Copied DB_PORT

4 secrets copied successfully.
```

The copy command accepts an optional destination argument (stage or vault:stage). If not provided, it will prompt you interactively.

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
- **`history <key>`** - View secret history
- **`copy <key> [destination]`** - Copy a single secret from current context to another stage or vault:stage
- **`copy only <pattern>`** - Copy secrets matching pattern from current context (prompts for destination)

### Analysis and Export

- **`diff <stage1> <stage2>`** - Compare secrets between stages
- **`export`** - Export secrets to .env format
- **`verify`** - Verify template placeholders

### Exiting the Shell

Exit the shell with:

```bash
ssm:development> exit
Goodbye!
```

Or use the keyboard shortcut `Ctrl+D`.

## Options Syntax

The shell uses a natural, dash-free syntax for options:

```bash
ssm:development> show unmask              # Show secrets with actual values
ssm:development> show json                # Output in JSON format
ssm:development> show env unmask          # Export format with real values
ssm:development> get NAME raw             # Get raw value without table
ssm:development> diff staging prod unmask # Compare with unmasked values
```

Options are simply words after the command - no dashes or equals signs needed.

## Tips and Tricks

### 1. Quick Context Switching

Use the `stage` command to quickly switch between environments:

```bash
ssm:development> stage staging
ssm:staging> set API_ENDPOINT "https://api-staging.example.com"
ssm:staging> stage production  
ssm:production> set API_ENDPOINT "https://api.example.com"
```

### 2. Fast Secret Copying

The copy command uses your current context as the source and accepts inline destinations:

```bash
# Current context serves as source
ssm:development> context
Current context: ssm:development

ssm:development> copy DB_PASSWORD staging        # Copies from current context to staging
ssm:development> copy API_KEY aws:production     # Copies from current context to aws:production
ssm:development> copy SECRET_KEY                 # Prompts for destination if not specified
```

### 3. Secret Name Patterns

Tab completion works with partial matches:

```bash
ssm:development> get AWS<TAB>
AWS_ACCESS_KEY_ID     AWS_SECRET_ACCESS_KEY     AWS_REGION
```

### 4. Command History

Use arrow keys to navigate through command history, making it easy to repeat or modify previous commands.

### 5. Multi-line Input

For complex values, the shell supports multi-line input:

```bash
ssm:development> set PRIVATE_KEY "-----BEGIN PRIVATE KEY-----
... MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEA
... [additional key content]
... -----END PRIVATE KEY-----"
Secret [/test-app/staging/PRIVATE_KEY] created in vault [ssm].
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
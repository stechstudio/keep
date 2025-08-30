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

### Tab Completion

The shell provides intelligent tab completion for:

- **Commands**: Type partial commands and press TAB to complete
- **Secret names**: When using `get`, `set`, `delete`, and other secret commands
- **Stage names**: When switching stages with the `stage` command
- **Vault names**: When switching vaults with the `vault` command

```bash
>>> get<TAB>                 # Shows all available secrets
>>> get DB_<TAB>             # Shows secrets starting with DB_
DB_HOST     DB_PASSWORD     DB_PORT     DB_USERNAME

>>> stage <TAB>              # Shows all configured stages
development     staging     production

>>> vault <TAB>              # Shows all configured vaults
ssm     secretsmanager     test
```

### Persistent Context

The shell maintains your current vault and stage context throughout your session:

```bash
>>> context
Current vault: ssm
Current stage: staging

>>> stage production
Switched to stage: production

>>> context
Current vault: ssm
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
Secret [/test-app/production/API_KEY] created in vault [ssm].

>>> get API_KEY
┌─────────┬───────┬───────────────┬──────────┐
│ Key     │ Vault │ Value         │ Revision │
├─────────┼───────┼───────────────┼──────────┤
│ API_KEY │ ssm   │ sk-1********* │ 1        │
└─────────┴───────┴───────────────┴──────────┘

>>> show
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
>>> diff staging production

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

>>> diff staging production unmask  # Show actual values
```

### Bulk Operations

The shell supports all Keep commands with natural syntax:

```bash
>>> copy only DB_*
Copying secrets from ssm:staging to ssm:production
✓ Copied DB_HOST
✓ Copied DB_PASSWORD
✓ Copied DB_USERNAME
✓ Copied DB_PORT

4 secrets copied successfully.
```

Note: The copy command uses the current stage as source and requires a `--to` option or uses default behavior based on your context.

### Creating New Stages

```bash
>>> stage:add testing
Stage 'testing' has been added.

>>> stage testing
Switched to stage: testing

>>> show
# Empty - new stage has no secrets yet

# To copy secrets, switch to source stage first:
>>> stage development
>>> copy only * --to=testing  
# Copies all secrets from development to testing
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
- **`history <key>`** - View secret history
- **`copy <key>`** - Copy a single secret (use --to option for destination)
- **`copy only <pattern>`** - Copy secrets matching pattern

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

## Options Syntax

The shell uses a natural, dash-free syntax for options:

```bash
>>> show unmask              # Show secrets with actual values
>>> show json                # Output in JSON format
>>> show env unmask          # Export format with real values
>>> get NAME raw             # Get raw value without table
>>> diff staging prod unmask # Compare with unmasked values
```

Options are simply words after the command - no dashes or equals signs needed.

## Tips and Tricks

### 1. Quick Context Switching

Use the `stage` command to quickly switch between environments:

```bash
>>> stage staging
>>> set API_ENDPOINT "https://api-staging.example.com"
>>> stage production  
>>> set API_ENDPOINT "https://api.example.com"
```

### 2. Secret Name Patterns

Tab completion works with partial matches:

```bash
>>> get AWS<TAB>
AWS_ACCESS_KEY_ID     AWS_SECRET_ACCESS_KEY     AWS_REGION
```

### 3. Command History

Use arrow keys to navigate through command history, making it easy to repeat or modify previous commands.

### 4. Multi-line Input

For complex values, the shell supports multi-line input:

```bash
>>> set PRIVATE_KEY "-----BEGIN PRIVATE KEY-----
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
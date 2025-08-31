# Interactive Shell

Keep provides a powerful interactive shell with tab completion and persistent context for efficient secret management. The shell is designed for human interaction with intuitive, natural commands - for scripting and automation, use the standalone CLI commands.

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
ssm:local>           # Working in SSM vault, local stage
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
ssm:local> get<TAB>                 # Shows all available secrets
ssm:local> get DB_<TAB>             # Shows secrets starting with DB_
DB_HOST     DB_PASSWORD     DB_PORT     DB_USERNAME

ssm:local> stage <TAB>              # Shows all configured stages
local     staging     production

ssm:local> vault <TAB>              # Shows all configured vaults
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
| `delete KEY [force]` | `d KEY` | Delete a secret (force skips confirmation) |
| `show [unmask]` | `ls` | Show all secrets (unmask shows actual values) |
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
┌─────────┬───────────────┬──────────┐
│ Key     │ Value         │ Revision │
├─────────┼───────────────┼──────────┤
│ API_KEY │ sk-1234567890 │ 1        │
└─────────┴───────────────┴──────────┘

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
ssm:local> diff staging production

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

```

### Bulk Operations

The shell supports all Keep commands with natural syntax. The copy command always uses your current context as the source:

```bash
# Current context: ssm:local
ssm:local> context
Current context: ssm:local

# Copy a single secret to another stage (same vault)
ssm:local> copy API_KEY production
Copying secret from ssm:local to ssm:production
✓ Copied API_KEY

# Copy to a different vault and stage
ssm:local> copy DB_PASSWORD aws-secrets:staging
Copying secret from ssm:local to aws-secrets:staging
✓ Copied DB_PASSWORD

# Copy with patterns (prompts for destination)
ssm:local> copy only DB_*
To (vault): ssm
To (stage): production
Copying secrets from ssm:local to ssm:production
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
- **`delete <key> [force]`** - Delete a secret (add 'force' to skip confirmation)
- **`show [unmask]`** - Show all secrets in current context (add 'unmask' to see actual values)
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
ssm:local> exit
Goodbye!
```

Or use the keyboard shortcut `Ctrl+D`.

## Options Syntax

The shell uses a natural, dash-free syntax for human-friendly interaction:

```bash
ssm:local> show unmask              # Show secrets with actual values
ssm:local> delete API_KEY force     # Delete without confirmation
ssm:local> diff staging production  # Compare secrets between stages
```

Options are simply words after the command - no dashes or equals signs needed. The shell focuses on human-readable output formats. For JSON, CSV, or other machine-readable formats, use the standalone CLI commands instead.

## Tips and Tricks

### 1. Quick Context Switching

Use the `stage` command to quickly switch between environments:

```bash
ssm:local> stage staging
ssm:staging> set API_ENDPOINT "https://api-staging.example.com"
ssm:staging> stage production  
ssm:production> set API_ENDPOINT "https://api.example.com"
```

### 2. Fast Secret Copying

The copy command uses your current context as the source and accepts inline destinations:

```bash
# Current context serves as source
ssm:local> context
Current context: ssm:local

ssm:local> copy DB_PASSWORD staging        # Copies from current context to staging
ssm:local> copy API_KEY aws:production     # Copies from current context to aws:production
ssm:local> copy SECRET_KEY                 # Prompts for destination if not specified
```

### 3. Secret Name Patterns

Tab completion works with partial matches:

```bash
ssm:local> get AWS<TAB>
AWS_ACCESS_KEY_ID     AWS_SECRET_ACCESS_KEY     AWS_REGION
```

### 4. Command History

Use arrow keys to navigate through command history, making it easy to repeat or modify previous commands.

### 5. Multi-line Input

For complex values, the shell supports multi-line input:

```bash
ssm:local> set PRIVATE_KEY "-----BEGIN PRIVATE KEY-----
... MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEA
... [additional key content]
... -----END PRIVATE KEY-----"
Secret [/test-app/staging/PRIVATE_KEY] created in vault [ssm].
```

## Shell vs CLI Commands

### When to Use the Shell

- **Interactive exploration**: Browsing secrets, switching contexts
- **Quick edits**: Setting or updating individual secrets
- **Context persistence**: Working within a single vault/stage for multiple operations
- **Human-friendly output**: Always displays formatted tables for easy reading

### When to Use CLI Commands

- **Scripting and automation**: CI/CD pipelines, automated deployments
- **Machine-readable output**: JSON format (`--format=json`), raw values (`--format=raw`)
- **Batch operations**: Processing multiple secrets programmatically
- **Integration**: Piping output to other tools or scripts

## Performance

The shell implements several optimizations:

- **Secret name caching**: Secret names are cached for 60 seconds to improve tab completion performance
- **Lazy loading**: Vaults are only loaded when accessed
- **Minimal overhead**: Direct command execution without shell spawning

## See Also

- [CLI Reference](/guide/reference/cli-reference) - Complete command documentation
- [Managing Secrets](/guide/managing-secrets/) - Secret management guide
- [Quick Start](/guide/quick-start) - Getting started with Keep
# Interactive Shell

Keep provides a powerful interactive shell with tab completion and persistent context for efficient secret management. The shell is designed for human interaction with intuitive, natural commands.

## Getting Started

Launch the interactive shell with the `shell` command:

```bash
keep shell
```

You can also start with a specific vault and env:

```bash
keep shell --vault=ssm --env=production
```

## Key Features

### Context-Aware Prompt

The shell prompt always displays your current vault and env:

```bash
ssm:local>           # Working in SSM vault, local env
aws:production>      # Working in AWS vault, production env
```

### Tab Completion

Intelligent tab completion for commands, secret names, envs, and vaults:

```bash
ssm:local> get<TAB>                 # Shows all available secrets
ssm:local> get DB_<TAB>             # Shows secrets starting with DB_
DB_HOST     DB_PASSWORD     DB_PORT     DB_USERNAME

ssm:local> env <TAB>                # Shows all configured envs
local     staging     production
```

### Command Shortcuts

Save keystrokes with built-in aliases:

| Full Command | Shortcut | Description |
|-------------|----------|-------------|
| `get KEY` | `g KEY` | Get a secret value |
| `set KEY VALUE` | `s KEY VALUE` | Set a secret |
| `delete KEY` | `d KEY` | Delete a secret |
| `show` | `ls` | Show all secrets |
| `use vault:env` | `u vault:env` | Switch context |
| `context` | `ctx` | Show current context |
| `exit` | `q` | Exit shell |

### Natural Syntax

The shell uses a dash-free syntax for human-friendly interaction:

```bash
ssm:local> show unmask              # Show secrets with actual values
ssm:local> delete API_KEY force     # Delete without confirmation
ssm:local> diff staging production  # Compare secrets between environments
```

## Common Workflows

### Quick Context Switching

```bash
# Switch env
ssm:local> env production
Switched to env: production

# Switch both vault and env
ssm:production> use secretsmanager:staging
Switched to vault: secretsmanager, env: staging

# Use shortcuts
secretsmanager:staging> u ssm:local
```

### Managing Secrets

```bash
# Set a new secret
ssm:local> set API_KEY "sk-1234567890"
Secret created in vault [ssm].

# Get a secret value
ssm:local> get API_KEY
┌─────────┬───────────────┬──────────┐
│ Key     │ Value         │ Revision │
├─────────┼───────────────┼──────────┤
│ API_KEY │ sk-1234567890 │ 1        │
└─────────┴───────────────┴──────────┘

# Show all secrets (masked by default)
ssm:local> show
┌─────────────┬─────────────────┬──────────┐
│ Key         │ Value           │ Revision │
├─────────────┼─────────────────┼──────────┤
│ API_KEY     │ sk-1*********   │ 1        │
│ DB_PASSWORD │ supe*********** │ 1        │
└─────────────┴─────────────────┴──────────┘

# Show unmasked values
ssm:local> show unmask
```

### Copying Secrets

The copy command uses your current context as the source:

```bash
# Copy a single secret to another env
ssm:local> copy API_KEY production
Copying secret from ssm:local to ssm:production
✓ Copied API_KEY

# Copy to a different vault and env
ssm:local> copy DB_PASSWORD secretsmanager:staging
✓ Copied DB_PASSWORD

# Copy with patterns
ssm:local> copy only DB_*
To (vault): ssm
To (env): production
✓ Copied DB_HOST
✓ Copied DB_PASSWORD
✓ Copied DB_USERNAME
4 secrets copied successfully.
```

### Searching and Renaming

```bash
# Search for text in secret values
ssm:production> search postgres
Found 2 secret(s) containing "postgres":
┌────────────────┬───────────────────┬──────────┐
│ Key            │ Value             │ Revision │
├────────────────┼───────────────────┼──────────┤
│ DATABASE_URL   │ post***********   │ 1        │
│ BACKUP_DB_URL  │ post***********   │ 1        │
└────────────────┴───────────────────┴──────────┘

# Rename a secret
ssm:local> rename OLD_API_KEY NEW_API_KEY
Proceed with rename? Yes
✓ Renamed [OLD_API_KEY] to [NEW_API_KEY]

# Force rename without confirmation
ssm:local> rename DB_PASS DB_PASSWORD force
✓ Renamed [DB_PASS] to [DB_PASSWORD]
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
│ APP_KEY      │ ✓ base********** │ ✓ base********** │ ✓ Identical │
└──────────────┴──────────────────┴──────────────────┴─────────────┘
```

## Tips & Tricks

### Interactive Selection

Omit arguments for interactive prompts:

```bash
>>> env
Select an env:
> local
  staging
  production

>>> vault
Select a vault:
> ssm
  secretsmanager
```

### Command History

- Navigate with `↑` / `↓` arrow keys
- History saved between sessions in `~/.keep_history`

### Multi-line Input

For complex values like certificates:

```bash
ssm:local> set PRIVATE_KEY "-----BEGIN PRIVATE KEY-----
... MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEA
... -----END PRIVATE KEY-----"
Secret created in vault [ssm].
```

### Color Coding

The shell uses colors for quick identification:
- **Green** ✓ - Success messages
- **Red** ✗ - Errors
- **Yellow** ⚠ - Warnings
- **Blue** - Context (vault:env)
- **Magenta** - Secret names

### Quick Productivity Tips

```bash
# Use aliases for speed
>>> g API_KEY         # get
>>> s NEW_KEY value   # set
>>> ls                # show all
>>> ctx               # context
>>> q                 # exit

# Tab complete partial names
>>> g A<TAB>          # Shows all secrets starting with A
>>> get API_<TAB>     # Shows all API_ secrets

# Review before bulk operations
>>> diff local staging
>>> copy only * staging

# Quick backup
>>> export
# Choose: all secrets → env format → file
```

## Shell vs CLI

**Use the Shell for:**
- Interactive exploration and browsing
- Quick edits and context switching
- Human-friendly formatted output
- Working within a single vault/env

**Use the CLI for:**
- Scripting and automation
- CI/CD pipelines
- Machine-readable output (JSON, raw)
- Batch operations

## Performance

The shell implements several optimizations:
- Secret names cached for 60 seconds
- Vaults loaded on-demand
- Direct command execution without spawning subshells

## Next Steps

- [Shell Command Reference](./reference) - Complete list of shell commands
- [CLI Overview](../cli/) - Command-line interface for automation
- [Web UI](../web-ui/) - Visual interface for secret management
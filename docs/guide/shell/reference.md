# Shell Command Reference

Complete reference for all interactive shell commands, shortcuts, and options.

## Core Commands

### Navigation Commands

**vault** - Switch vault context
```bash
ssm:local> vault secretsmanager
Switched to vault: secretsmanager

ssm:local> vault
# Interactive selection menu appears
```

**env** - Switch environment context  
```bash
ssm:local> env production
Switched to env: production

ssm:local> env
# Interactive selection menu appears
```

**use** - Switch both vault and env at once
```bash
ssm:local> use ssm:production
Switched to: ssm:production

ssm:local> u secretsmanager:staging  # Using alias
```

**info** - Show Keep configuration
```bash
ssm:local> info
Application: My App
Namespace: MYAPP_
Default Vault: ssm
Default Environment: local
```

**context** / **ctx** - Show current context
```bash
ssm:local> context
Vault: ssm
Environment: production

ssm:local> ctx  # Using alias
```

### Secret Management

**set** - Create or update a secret
```bash
ssm:local> set API_KEY
Enter value (hidden): ********
✓ Set API_KEY

ssm:local> set DB_URL postgresql://localhost/db
✓ Set DB_URL

ssm:local> s DEBUG_MODE true  # Using alias
```

**get** - Retrieve a secret value
```bash
ssm:local> get API_KEY
┌──────────┬────────────────┬─────┐
│ Key      │ Value          │ Rev │
├──────────┼────────────────┼─────┤
│ API_KEY  │ sk_l****       │ 3   │
└──────────┴────────────────┴─────┘

ssm:local> g DB_HOST  # Using alias
```

**show** / **ls** - List all secrets
```bash
ssm:local> show
┌─────────────────┬────────────────┬──────────┐
│ Key             │ Value          │ Revision │
├─────────────────┼────────────────┼──────────┤
│ API_KEY         │ sk_l****       │ 3        │
│ DATABASE_URL    │ post****       │ 1        │
│ DEBUG_MODE      │ ****           │ 2        │
└─────────────────┴────────────────┴──────────┘

ssm:local> show unmask  # Show actual values
ssm:local> ls  # Using alias
```

**delete** - Remove a secret
```bash
ssm:local> delete OLD_KEY
Delete OLD_KEY? (y/N): y
✓ Deleted OLD_KEY

ssm:local> delete TEMP_KEY force  # Skip confirmation
ssm:local> d UNUSED_VAR  # Using alias
```

**rename** - Rename a secret
```bash
ssm:local> rename OLD_NAME NEW_NAME
Rename OLD_NAME to NEW_NAME? (y/N): y
✓ Renamed OLD_NAME to NEW_NAME

ssm:local> rename API_V1 API_KEY force
```

**history** - View secret version history
```bash
ssm:local> history API_KEY
History for secret: API_KEY
┌─────────┬────────────┬──────────┬─────────────────────┬──────────────┐
│ Version │ Value      │ Type     │ Modified Date       │ Modified By  │
├─────────┼────────────┼──────────┼─────────────────────┼──────────────┤
│ 3       │ sk_n****   │ String   │ 2024-01-15 10:30:00 │ admin        │
│ 2       │ sk_o****   │ String   │ 2024-01-10 14:22:00 │ admin        │
│ 1       │ sk_t****   │ String   │ 2024-01-05 09:15:00 │ admin        │
└─────────┴────────────┴──────────┴─────────────────────┴──────────────┘

ssm:local> history API_KEY unmask  # Show actual values
```

**search** - Search for secrets by value
```bash
ssm:local> search postgres
Found 3 secrets containing "postgres":
┌──────────────┬────────────────┬──────────┐
│ Key          │ Value          │ Revision │
├──────────────┼────────────────┼──────────┤
│ DB_URL       │ post****       │ 2        │
│ BACKUP_DB    │ post****       │ 1        │
│ TEST_DB_URL  │ post****       │ 1        │
└──────────────┴────────────────┴──────────┘

ssm:local> search api-key unmask
# Shows matches with unmasked values

ssm:local> search Token case-sensitive
# Case-sensitive search
```

### Cross-Environment Operations

**copy** - Copy secrets between environments
```bash
ssm:local> copy API_KEY production
✓ Copied API_KEY to production

ssm:local> copy API_KEY production overwrite  # Overwrite if exists
ssm:local> copy API_KEY production dry-run    # Preview without copying

ssm:local> copy only DB_* staging
✓ Copied 3 secrets matching DB_* to staging

ssm:local> copy SECRET_KEY
# Prompts for destination interactively
```

**diff** - Compare environments
```bash
ssm:local> diff local production
Secret Comparison Matrix
┌──────────┬───────────┬──────────────┬──────────────┐
│ Key      │ local     │ production   │ Status       │
├──────────┼───────────┼──────────────┼──────────────┤
│ API_KEY  │ ✓ sk_d*** │ ✓ sk_l****   │ Different    │
│ DEBUG    │ ✓ ****    │ ✓ ****       │ Different    │
│ NEW_VAR  │ ✓ test*** │ —            │ Incomplete   │
│ DB_HOST  │ ✓ loca*** │ ✓ loca****   │ Identical    │
└──────────┴───────────┴──────────────┴──────────────┘

Summary:
• Total secrets: 4
• Identical across all environments: 1 (25%)
• Different values: 2 (50%)
• Missing in some envs: 1 (25%)
• Environments compared: 2
```

### Import/Export

**export** - Interactive export to file or screen
```bash
ssm:local> export
# Interactive prompts:
# 1. Export mode (all/template/filtered)
# 2. Format (env/json)
# 3. Destination (screen/file)

ssm:local> export json
# Quick JSON export (still prompts for destination)

ssm:local> export env
# Quick env format export
```

**import** - Import secrets from .env file
```bash
ssm:local> import .env
# Imports secrets from file

ssm:local> import .env overwrite      # Overwrite existing secrets
ssm:local> import .env skip-existing  # Skip existing secrets
ssm:local> import .env dry-run        # Preview without importing
ssm:local> import .env dry-run overwrite  # Flags can be combined in any order
```

### Verification

**verify** - Test vault permissions
```bash
ssm:local> verify
Checking vault access permissions...
Keep Vault Verification Results
┌────────────────┬────────────┬──────┬───────┬──────┬─────────┬────────┐
│ Vault          │ Environment      │ List │ Write │ Read │ History │ Delete │
├────────────────┼────────────┼──────┼───────┼──────┼─────────┼────────┤
│ ssm            │ local      │ ✓    │ ✓     │ ✓    │ ✓       │ ✓      │
│ ssm            │ staging    │ ✓    │ ✓     │ ✓    │ ✓       │ ✓      │
│ ssm            │ production │ ✓    │ ✗     │ ✓    │ ✓       │ -      │
│ secretsmanager │ local      │ ✗    │ ✗     │ ?    │ ?       │ -      │
└────────────────┴────────────┴──────┴───────┴──────┴─────────┴────────┘

Summary:
• Total vault/env combinations tested: 4
• Full access (list + write + read + history): 2
• Read + History access (list + read + history): 1
• Read-only access (list + read): 0
• List-only access (list only): 0
• No access (none): 1

Legend:
✓ = Success
✗ = Failed/No Permission
? = Unknown (unable to test)
⚠ = Cleanup failed (test secret may remain)
- = Not applicable
```

## Command Shortcuts & Aliases

### Command Aliases
- `g` → `get`
- `s` → `set`
- `d` → `delete`
- `ls` → `show`
- `u` → `use`
- `ctx` → `context`
- `cls` → `clear`
- `q` → `quit`
- `?` → `help`

### Examples
```bash
ssm:local> g API_KEY           # get API_KEY
ssm:local> s NEW_VAR value     # set NEW_VAR value
ssm:local> d OLD_VAR force     # delete OLD_VAR force
ssm:local> ls unmask           # show unmask
ssm:local> u ssm:staging       # use ssm:staging
ssm:local> ctx                 # context
ssm:local> cls                 # clear
ssm:local> q                   # exit
```

## Tab Completion

The shell provides intelligent tab completion:

### Secret Names
```bash
ssm:local> get DB_<TAB>
DB_HOST  DB_NAME  DB_PASSWORD  DB_PORT  DB_USER

ssm:local> get DB_P<TAB>
DB_PASSWORD  DB_PORT
```

### Commands
```bash
ssm:local> del<TAB>
delete

ssm:local> exp<TAB>
export
```

### Vault/Env Names
```bash
ssm:local> vault s<TAB>
secretsmanager  ssm

ssm:local> env prod<TAB>
production
```

## Special Commands

### Shell Management

**clear** / **cls** - Clear the screen
```bash
ssm:local> clear
ssm:local> cls  # Alias
```

**help** / **?** - Show available commands
```bash
ssm:local> help
Keep Shell Commands

Secret Management
  get <key>                Get a secret value (alias: g)
  set <key> <value>        Set a secret (alias: s)
  delete <key> [force]     Delete a secret (alias: d)
  show [unmask]            Show all secrets (alias: ls)
  history <key> [unmask]    View secret history
  rename <old> <new>       Rename a secret
  search <query>           Search for secrets containing text
  copy <key> [destination] Copy single secret
  copy only <pattern>      Copy secrets matching pattern
  diff <env1> <env2>       Compare secrets between environments

Context Management
  env <name>               Switch to a different env
  vault <name>             Switch to a different vault
  use <vault:env>          Switch both vault and env (alias: u)
  context                  Show current context (alias: ctx)

Import, Export & Analysis
  import <file> [flags]    Import secrets from .env file
  export                   Export secrets interactively
  verify                   Verify vault setup and permissions
  info                     Show Keep information

Other
  exit                     Exit the shell (or Ctrl+D)
  help                     Show this help message (alias: ?)
  clear                    Clear the screen (alias: cls)
  colors                   Show color scheme

ssm:local> help get
# Shows detailed help for 'get' command

ssm:local> ? set
# Shows help for 'set' using alias
```

**colors** - Display color scheme
```bash
ssm:local> colors

=== Shell Color Scheme ===

✓ Success message - operations completed successfully
→ Info message - general information
⚠ Warning message - attention needed
✗ Error message - something went wrong

ssm:production - Vault and environment context
DB_PASSWORD - Secret names
get - Command names
set - Command suggestions
This is neutral descriptive text
```

**exit** / **quit** / **q** - Leave the shell
```bash
ssm:local> exit
Goodbye!

ssm:local> quit  # Alternative
ssm:local> q     # Short alias
# Or press Ctrl+D
```

## Command Options

Most commands support additional options:

### Common Options
- `unmask` - Show actual values instead of masked
- `force` - Skip confirmation prompts

### Examples
```bash
ssm:local> show unmask
ssm:local> delete TEMP_KEY force
ssm:local> rename OLD NEW force
ssm:local> search text unmask
```

## Pattern Matching

The `copy` command supports pattern matching:
```bash
ssm:local> copy only DB_* staging
# Copies all secrets starting with DB_

ssm:local> copy only API_*,AUTH_* production
# Copies multiple patterns
```

## Interactive Features

Many commands become interactive when arguments are omitted:

```bash
ssm:local> env
# Shows interactive env selector

ssm:local> vault  
# Shows interactive vault selector

ssm:local> copy SECRET_KEY
# Prompts for destination

ssm:local> export
# Full interactive export wizard
```

## Important Notes

### Shell-Only Features
- Interactive prompts for env/vault selection
- Enhanced export wizard with guided prompts
- Command aliases and shortcuts
- Tab completion
- Command history in `~/.keep_history`

### CLI-Only Commands
These commands are not available in the shell:
- `init` - Use the CLI: `keep init`
- `vault:add` / `vault:edit` / `vault:delete` - Use the CLI for vault management
- `env:add` / `env:remove` - Use the CLI for environment management

### Security Notes
- The shell masks secret values by default using `****` or showing only first 4 characters
- Values longer than 24 characters are truncated in masked display
- Command history redacts sensitive values
- Use `unmask` option carefully in shared environments

### Masking Format
Keep uses a consistent masking approach:
- Values ≤ 8 characters: `****`
- Values > 8 characters: First 4 chars + `*` for remaining length
- Values > 24 characters (masked): Truncated to 24 chars with `(N chars)` suffix
- Example: `sk_live_abcdef123456` → `sk_l****************`
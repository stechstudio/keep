# Shell Commands & Shortcuts

The Keep interactive shell provides shortcuts and enhanced versions of CLI commands optimized for interactive use.

## Core Commands

### Navigation Commands

**vault** - Switch vault context
```bash
>>> vault secretsmanager
Switched to vault: secretsmanager

>>> vault
# Interactive selection menu appears
```

**stage** - Switch stage context  
```bash
>>> stage production
Switched to stage: production

>>> stage
# Interactive selection menu appears
```

**use** - Switch both vault and stage at once
```bash
>>> use ssm:production
Switched to: ssm:production

>>> u secretsmanager:staging  # Using alias
```

**info** - Show Keep configuration
```bash
>>> info
Application: My App
Namespace: MYAPP_
Default Vault: ssm
Default Stage: local
```

**context** / **ctx** - Show current context
```bash
>>> context
Vault: ssm
Stage: production

>>> ctx  # Using alias
```

### Secret Management

**set** - Create or update a secret
```bash
>>> set API_KEY
Enter value (hidden): ********
✓ Set API_KEY

>>> set DB_URL postgresql://localhost/db
✓ Set DB_URL

>>> s DEBUG_MODE true  # Using alias
```

**get** - Retrieve a secret value
```bash
>>> get API_KEY
┌──────────┬────────────────┬─────┐
│ Key      │ Value          │ Rev │
├──────────┼────────────────┼─────┤
│ API_KEY  │ sk_l****       │ 3   │
└──────────┴────────────────┴─────┘

>>> g DB_HOST  # Using alias
```

**show** / **ls** - List all secrets
```bash
>>> show
┌─────────────────┬────────────────┬──────────┐
│ Key             │ Value          │ Revision │
├─────────────────┼────────────────┼──────────┤
│ API_KEY         │ sk_l****       │ 3        │
│ DATABASE_URL    │ post****       │ 1        │
│ DEBUG_MODE      │ ****           │ 2        │
└─────────────────┴────────────────┴──────────┘

>>> show unmask  # Show actual values
>>> ls  # Using alias
```

**delete** - Remove a secret
```bash
>>> delete OLD_KEY
Delete OLD_KEY? (y/N): y
✓ Deleted OLD_KEY

>>> delete TEMP_KEY force  # Skip confirmation
>>> d UNUSED_VAR  # Using alias
```

**rename** - Rename a secret
```bash
>>> rename OLD_NAME NEW_NAME
Rename OLD_NAME to NEW_NAME? (y/N): y
✓ Renamed OLD_NAME to NEW_NAME

>>> rename API_V1 API_KEY force
```

**history** - View secret version history
```bash
>>> history API_KEY
History for secret: API_KEY
┌─────────┬────────────┬──────────┬─────────────────────┬──────────────┐
│ Version │ Value      │ Type     │ Modified Date       │ Modified By  │
├─────────┼────────────┼──────────┼─────────────────────┼──────────────┤
│ 3       │ sk_n****   │ String   │ 2024-01-15 10:30:00 │ admin        │
│ 2       │ sk_o****   │ String   │ 2024-01-10 14:22:00 │ admin        │
│ 1       │ sk_t****   │ String   │ 2024-01-05 09:15:00 │ admin        │
└─────────┴────────────┴──────────┴─────────────────────┴──────────────┘
```

**search** - Search for secrets by value
```bash
>>> search postgres
Found 3 secrets containing "postgres":
┌──────────────┬────────────────┬──────────┐
│ Key          │ Value          │ Revision │
├──────────────┼────────────────┼──────────┤
│ DB_URL       │ post****       │ 2        │
│ BACKUP_DB    │ post****       │ 1        │
│ TEST_DB_URL  │ post****       │ 1        │
└──────────────┴────────────────┴──────────┘

>>> search api-key unmask
# Shows matches with unmasked values

>>> search Token case-sensitive
# Case-sensitive search
```

### Cross-Environment Operations

**copy** - Copy secrets between stages
```bash
>>> copy API_KEY production
✓ Copied API_KEY to production

>>> copy only DB_* staging
✓ Copied 3 secrets matching DB_* to staging

>>> copy SECRET_KEY
# Prompts for destination interactively
```

**diff** - Compare environments
```bash
>>> diff local production
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
• Identical across all stages: 1 (25%)
• Different values: 2 (50%)
• Missing in some stages: 1 (25%)
• Stages compared: 2
```

### Import/Export

**export** - Interactive export to file or screen
```bash
>>> export
# Interactive prompts:
# 1. Export mode (all/template/filtered)
# 2. Format (env/json)
# 3. Destination (screen/file)

>>> export json
# Quick JSON export (still prompts for destination)

>>> export env
# Quick env format export
```

Note: The `import` command is only available in the CLI, not the shell.

### Verification

**verify** - Test vault permissions
```bash
>>> verify
Testing vault permissions...
✓ ssm:production
  Read: ✓
  Write: ✓
  List: ✓
  Delete: ✓
✗ secretsmanager:production
  Error: No credentials configured
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
- `q` → `exit`
- `?` → `help`

### Examples
```bash
>>> g API_KEY           # get API_KEY
>>> s NEW_VAR value     # set NEW_VAR value
>>> d OLD_VAR force     # delete OLD_VAR force
>>> ls unmask           # show unmask
>>> u ssm:staging       # use ssm:staging
>>> ctx                 # context
>>> cls                 # clear
>>> q                   # exit
```

## Tab Completion

The shell provides intelligent tab completion:

### Secret Names
```bash
>>> get DB_<TAB>
DB_HOST  DB_NAME  DB_PASSWORD  DB_PORT  DB_USER

>>> get DB_P<TAB>
DB_PASSWORD  DB_PORT
```

### Commands
```bash
>>> del<TAB>
delete

>>> exp<TAB>
export
```

### Vault/Stage Names
```bash
>>> vault s<TAB>
secretsmanager  ssm

>>> stage prod<TAB>
production
```

## Special Commands

### Shell Management

**clear** / **cls** - Clear the screen
```bash
>>> clear
>>> cls  # Alias
```

**help** / **?** - Show available commands
```bash
>>> help
Keep Shell Commands

Secret Management
  get <key>                Get a secret value (alias: g)
  set <key> <value>        Set a secret (alias: s)
  delete <key> [force]     Delete a secret (alias: d)
  show [unmask]            Show all secrets (alias: ls)
  history <key>            View secret history
  rename <old> <new>       Rename a secret
  search <query>           Search for secrets containing text
  copy <key> [destination] Copy single secret
  copy only <pattern>      Copy secrets matching pattern
  diff <stage1> <stage2>   Compare secrets between stages

Context Management
  stage <name>             Switch to a different stage
  vault <name>             Switch to a different vault
  use <vault:stage>        Switch both vault and stage (alias: u)
  context                  Show current context (alias: ctx)

Analysis & Export
  export                   Export secrets interactively
  verify                   Verify vault setup and permissions
  info                     Show Keep information

Other
  exit                     Exit the shell (or Ctrl+D)
  help                     Show this help message (alias: ?)
  clear                    Clear the screen (alias: cls)
  colors                   Show color scheme

>>> help get
# Shows detailed help for 'get' command

>>> ? set
# Shows help for 'set' using alias
```

**colors** - Display color scheme
```bash
>>> colors

=== Shell Color Scheme ===

✓ Success message - operations completed successfully
→ Info message - general information
⚠ Warning message - attention needed
✗ Error message - something went wrong

ssm:production - Vault and stage context
DB_PASSWORD - Secret names
get - Command names
set - Command suggestions
This is neutral descriptive text
```

**exit** / **quit** / **q** - Leave the shell
```bash
>>> exit
Goodbye!

>>> quit  # Alternative
>>> q     # Short alias
# Or press Ctrl+D
```

## Command Options

Most commands support additional options:

### Common Options
- `unmask` - Show actual values instead of masked
- `force` - Skip confirmation prompts

### Examples
```bash
>>> show unmask
>>> delete TEMP_KEY force
>>> rename OLD NEW force
>>> search text unmask
```

## Pattern Matching

The `copy` command supports pattern matching:
```bash
>>> copy only DB_* staging
# Copies all secrets starting with DB_

>>> copy only API_*,AUTH_* production
# Copies multiple patterns
```

## Interactive Features

Many commands become interactive when arguments are omitted:

```bash
>>> stage
# Shows interactive stage selector

>>> vault  
# Shows interactive vault selector

>>> copy SECRET_KEY
# Prompts for destination

>>> export
# Full interactive export wizard
```

## Important Notes

### Shell-Only Features
- Interactive prompts for stage/vault selection
- Enhanced export wizard with guided prompts
- Command aliases and shortcuts
- Tab completion
- Command history in `~/.keep_history`

### CLI-Only Commands
These commands are not available in the shell:
- `import` - Use the CLI: `keep import file.env`
- `push` / `pull` - Use the CLI for bulk operations
- `configure` - Use the CLI: `keep configure`

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
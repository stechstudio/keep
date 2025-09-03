# Shell Commands & Shortcuts

The Keep interactive shell provides shortcuts and enhanced versions of CLI commands optimized for interactive use.

## Core Commands

### Navigation Commands

**vault** - Switch vault context
```bash
>>> vault secretsmanager
Switched to vault: secretsmanager

>>> vault
Current vault: secretsmanager
Available: ssm, secretsmanager
```

**stage** / **use** - Switch stage context  
```bash
>>> stage production
Switched to stage: production

>>> use local
Switched to stage: local
```

**info** - Show current context
```bash
>>> info
Vault: ssm
Stage: production
Namespace: MYAPP_
Secrets: 42
```

### Secret Management

**set** - Create or update a secret
```bash
>>> set API_KEY
Enter value (hidden): ********
✓ Set API_KEY

>>> set DB_URL "postgresql://localhost/db"
✓ Set DB_URL
```

**get** - Retrieve a secret value
```bash
>>> get API_KEY
API_KEY=sk_live_****3f2a

>>> get API_KEY --unmask
API_KEY=sk_live_abcdef123456
```

**list** / **ls** - List all secrets
```bash
>>> list
┌─────────────────┬──────────────┬──────────────┐
│ Key             │ Value        │ Modified     │
├─────────────────┼──────────────┼──────────────┤
│ API_KEY         │ sk_****3f2a  │ 2 hours ago  │
│ DATABASE_URL    │ post****5432 │ 3 days ago   │
└─────────────────┴──────────────┴──────────────┘

>>> ls DB_*  # With pattern
```

**delete** / **rm** - Remove a secret
```bash
>>> delete OLD_KEY
Delete OLD_KEY? (y/N): y
✓ Deleted OLD_KEY

>>> rm TEMP_* --force  # Skip confirmation
```

### Cross-Environment Operations

**copy** / **cp** - Copy secrets between stages
```bash
>>> copy API_KEY production
✓ Copied API_KEY to production

>>> cp DB_* staging  # Copy multiple
✓ Copied 3 secrets to staging
```

**diff** - Compare environments
```bash
>>> diff local production
┌──────────┬─────────┬────────────┬─────────┐
│ Key      │ local   │ production │ Status  │
├──────────┼─────────┼────────────┼─────────┤
│ API_KEY  │ dev_key │ prod_key   │ ≠       │
│ DEBUG    │ true    │ false      │ ≠       │
│ NEW_VAR  │ value   │ -          │ local   │
└──────────┴─────────┴────────────┴─────────┘
```

**push** - Copy all local secrets to another stage
```bash
>>> push staging
This will copy 15 secrets to staging
Continue? (y/N): y
✓ Pushed to staging
```

**pull** - Copy all secrets from another stage
```bash
>>> pull production
This will overwrite 12 local secrets
Continue? (y/N): y
✓ Pulled from production
```

### Import/Export

**export** - Export to file
```bash
>>> export
# Outputs to screen

>>> export .env
✓ Exported to .env

>>> export --format=json > config.json
✓ Exported to config.json
```

**import** - Import from file
```bash
>>> import .env
Preview:
  NEW: 5 secrets
  UPDATE: 3 secrets
  SKIP: 2 secrets
Continue? (y/N): y
✓ Imported 8 secrets
```

## Shortcuts & Aliases

### Command Shortcuts
- `ls` → `list`
- `rm` → `delete`
- `cp` → `copy`
- `use` → `stage`

### Quick Switches
- `local` → `stage local`
- `staging` → `stage staging`  
- `production` → `stage production`
- `prod` → `stage production`

### Shorthand Syntax
```bash
# Vault:stage notation
>>> list ssm:production
>>> get secretsmanager:staging/API_KEY

# Pattern matching
>>> list DB_*
>>> delete TEMP_* --force
>>> copy API_* production
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

**clear** - Clear the screen
```bash
>>> clear
```

**history** - Show command history
```bash
>>> history
1: vault ssm
2: stage production
3: list
4: get API_KEY
```

**help** - Show available commands
```bash
>>> help
Available commands:
  vault     - Switch vault context
  stage     - Switch stage context
  set       - Set a secret value
  get       - Get a secret value
  ...
```

**exit** / **quit** - Leave the shell
```bash
>>> exit
Goodbye!
```

### Advanced Features

**mask** / **unmask** - Toggle value masking
```bash
>>> unmask
⚠ Values will be shown unmasked

>>> mask
✓ Values will be masked
```

**verify** - Test vault permissions
```bash
>>> verify
✓ ssm:production - Read, Write, List
✗ secretsmanager:production - No access
```

## Command Options

Most commands support options similar to their CLI counterparts:

### Common Options
- `--vault=<name>` - Specify vault
- `--stage=<name>` - Specify stage  
- `--force` - Skip confirmations
- `--unmask` - Show actual values

### Examples
```bash
>>> list --unmask
>>> get API_KEY --vault=secretsmanager
>>> delete TEMP_* --force
>>> copy --from=local --to=production API_KEY
```
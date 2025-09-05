# Shell Tips & Tricks

Master the Keep interactive shell with these productivity tips and techniques.

## Quick Tips

### Use Command Aliases
Save keystrokes with built-in aliases:
```bash
# Instead of:
>>> get API_KEY
>>> set DB_HOST localhost
>>> delete OLD_KEY

# Use shortcuts:
>>> g API_KEY
>>> s DB_HOST localhost  
>>> d OLD_KEY
```

**Available aliases:**
- `g` → `get`
- `s` → `set`
- `d` → `delete`
- `ls` → `show`
- `u` → `use`
- `ctx` → `context`
- `cls` → `clear`
- `q` → `exit`
- `?` → `help`

### Interactive Selection
Omit arguments for interactive prompts:
```bash
>>> stage
Select a stage:
> local
  staging
  production

>>> vault
Select a vault:
> ssm
  secretsmanager
```

### Tab Completion
Tab completion works for commands and secret names:
```bash
>>> g<TAB>
get

>>> get DB_<TAB>
DB_HOST  DB_NAME  DB_PASSWORD  DB_PORT  DB_USER
```

### Command History
Navigate through previous commands:
- `↑` / `↓` - Browse command history
- History is saved between sessions in `~/.keep_history`

## Working with Secrets

### Quick Context Switching
```bash
# Switch stage
>>> stage production

# Switch vault
>>> vault secretsmanager

# Switch both at once
>>> use ssm:staging
```

### Copy Patterns
Copy multiple secrets matching a pattern:
```bash
# Copy all DB_ secrets to staging
>>> copy only DB_* staging

# Copy all API_ secrets
>>> copy only API_* production
```

### Interactive Export
The shell provides an enhanced export experience:
```bash
>>> export
# Prompts for:
# - Export mode (all/template/filtered)
# - Format (env/json)
# - Destination (screen/file)
```

### Search for Values
Find secrets containing specific text:
```bash
>>> search "postgres"
# Shows all secrets with "postgres" in their value

>>> search "api-key" unmask
# Show actual values in results
```

## Productivity Workflows

### Environment Comparison
```bash
# Compare two stages
>>> diff local production

# See what's different
>>> diff staging production
```

### Quick Value Check
```bash
# View a specific secret
>>> get API_KEY

# View all secrets
>>> show

# View unmasked values
>>> show unmask
```

### Rename Operations
```bash
# Rename a secret
>>> rename OLD_NAME NEW_NAME

# Skip confirmation
>>> rename OLD_KEY NEW_KEY force
```

### Secret History
View the revision history of any secret:
```bash
>>> history API_KEY
# Shows all versions with timestamps
```

## Shell Management

### Get Help
```bash
# See all commands
>>> help

# Get help for specific command
>>> help get
>>> ? set
```

### Check Context
```bash
# See current vault and stage
>>> context
# Or use the alias
>>> ctx
```

### Clear Screen
```bash
>>> clear
# Or
>>> cls
```

### Exit Shell
```bash
>>> exit
# Or
>>> quit
# Or
>>> q
# Or press Ctrl+D
```

## Color Coding

The shell uses colors to help you quickly identify information:
- **Green** ✓ - Success messages
- **Red** ✗ - Errors
- **Yellow** ⚠ - Warnings
- **Blue** - Context (vault:stage)
- **Magenta** - Secret names
- **White** - Commands

View the color scheme:
```bash
>>> colors
```

## Best Practices

### Start with Context
Always verify your context when starting:
```bash
>>> ctx
Vault: ssm
Stage: local
```

### Use Aliases
Embrace the shortcuts to work faster:
```bash
>>> g API_KEY         # get
>>> s NEW_KEY value   # set
>>> ls                # show all
```

### Review Before Actions
Use diff to review changes before copying:
```bash
>>> diff local staging
>>> copy only * staging
```

### Leverage Tab Completion
Don't type full secret names:
```bash
>>> g A<TAB>          # Shows all secrets starting with A
>>> get API_<TAB>     # Shows all API_ secrets
```

## Common Workflows

### Development Setup
```bash
# Start in local
>>> stage local
>>> import .env.example

# View what was imported
>>> show

# Copy to your development stage
>>> copy only * development
```

### Production Deployment
```bash
# Compare staging to production
>>> diff staging production

# Switch to staging
>>> stage staging

# Copy verified secrets
>>> copy only API_* production
>>> copy only DB_* production

# Verify
>>> diff staging production
```

### Quick Backup
```bash
# Export current stage
>>> export
# Choose: all secrets → env format → file
# Enter filename: backup.env
```

## Troubleshooting

### Command Not Found
If a command isn't recognized:
```bash
>>> help
# Shows all available commands
```

### Wrong Context
To quickly reset context:
```bash
>>> use ssm:local
# Sets both vault and stage
```

### Need Unmasked Values
Most commands support an `unmask` option:
```bash
>>> show unmask
>>> search "text" unmask
```

### Lost in History
Your command history is saved in:
```bash
~/.keep_history
```

## Tips for Power Users

### Quick Environment Switch
Use `u` for fast context changes:
```bash
>>> u ssm:production
>>> u secretsmanager:staging
```

### Batch Operations
Use patterns with copy:
```bash
>>> copy only DB_*,API_*,AUTH_* production
```

### Verify Before Changes
Always verify permissions:
```bash
>>> verify
✓ ssm:local - Read, Write, List
```

### Export for Review
Export to screen for quick review:
```bash
>>> export
# Choose: all → env → screen
```

Remember: The shell is designed for interactive use. For scripting or automation, use the CLI commands directly.
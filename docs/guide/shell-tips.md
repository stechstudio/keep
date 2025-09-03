# Shell Tips & Tricks

Master the Keep interactive shell with these productivity tips and advanced techniques.

## Productivity Tips

### Quick Context Switching

**Use Shortcuts**
```bash
# Instead of:
>>> stage local
>>> vault ssm

# Just type:
>>> local
>>> ssm
```

**Chain Commands**
```bash
# Switch and list in one line
>>> production && list

# Copy and verify
>>> copy API_KEY staging && get staging/API_KEY
```

### Efficient Secret Management

**Bulk Operations with Patterns**
```bash
# Set multiple related secrets
>>> set DB_HOST=localhost
>>> set DB_PORT=5432
>>> set DB_USER=admin
>>> set DB_NAME=myapp

# Or copy them all at once
>>> copy DB_* production
```

**Use Tab Completion Aggressively**
```bash
# Don't type full names
>>> get A<TAB>     # Shows all secrets starting with A
>>> get API_<TAB>  # Shows all API_ secrets
```

### Command History

**Navigate History**
- `↑` / `↓` - Browse previous commands
- `Ctrl+R` - Reverse search history
- `!!` - Repeat last command
- `!get` - Repeat last command starting with "get"

**History Tricks**
```bash
# Edit and rerun previous command
>>> get API_KEY
>>> ^KEY^TOKEN    # Changes API_KEY to API_TOKEN

# Reuse arguments
>>> get DATABASE_URL
>>> set !$        # Uses DATABASE_URL from previous command
```

## Advanced Techniques

### Contextual Operations

**Temporary Context Switch**
```bash
# Check production without switching
>>> get production/API_KEY
>>> list --stage=production

# Your context remains unchanged
```

**Cross-Vault Operations**
```bash
# Copy between vaults
>>> copy ssm:local/API_KEY secretsmanager:production/API_KEY

# Compare different vaults
>>> diff ssm:production secretsmanager:production
```

### Scripting in the Shell

**Multi-Line Values**
```bash
>>> set SSL_CERT
Enter value (Ctrl+D when done):
-----BEGIN CERTIFICATE-----
MIIDXTCCAkWgAwIBAgIJAKLdQVPy90WJMA0GCSqGSIb3DQEBCwUAMEUxCzAJBgNV
...
-----END CERTIFICATE-----
<Ctrl+D>
✓ Set SSL_CERT
```

**Command Sequences**
```bash
# Create a set of related secrets
>>> for env in local staging production; do \
      stage $env && \
      set DEPLOY_DATE="$(date)" && \
      set VERSION="1.2.3"; \
    done
```

### Output Processing

**Pipe to External Commands**
```bash
# Search with grep
>>> list | grep -i database

# Count secrets
>>> list | wc -l

# Export and process
>>> export --format=json | jq '.API_KEY'
```

**Save to Files**
```bash
# Redirect output
>>> list > secret-list.txt
>>> export --format=json > config.json

# Append to files
>>> get API_KEY >> keys.txt
```

## Workflow Optimizations

### Environment Promotion Workflow

```bash
# 1. Start in local
>>> local
>>> list

# 2. Review what will be promoted
>>> diff local staging

# 3. Push all to staging
>>> push staging

# 4. Switch and verify
>>> staging
>>> list

# 5. Test specific values
>>> get API_KEY --unmask

# 6. Promote to production
>>> push production
```

### Quick Backup and Restore

**Backup Current Environment**
```bash
>>> export backup-$(date +%Y%m%d).env
✓ Exported to backup-20240115.env
```

**Restore from Backup**
```bash
>>> import backup-20240115.env --force
✓ Imported 42 secrets
```

### Development to Production

```bash
# Set up local development
>>> local
>>> import .env.development

# Test in staging
>>> copy * staging
>>> stage staging
>>> verify

# Selective production update
>>> production
>>> pull staging --only=API_*,DB_*
```

## Troubleshooting in Shell

### Debug Context Issues

```bash
# Where am I?
>>> info

# What's available?
>>> vault
>>> stage

# Test access
>>> verify
```

### Fix Common Problems

**Wrong Vault/Stage**
```bash
# Quick reset to defaults
>>> vault $(keep info | grep "Default vault" | cut -d: -f2)
>>> stage local
```

**Accidental Deletion**
```bash
# Check if it exists elsewhere
>>> diff local staging production | grep DELETED_KEY

# Restore from another stage
>>> copy staging/DELETED_KEY local
```

**Mass Cleanup**
```bash
# Delete all temporary secrets
>>> list TEMP_*
>>> delete TEMP_* --force

# Remove old prefixed secrets
>>> list | grep OLD_ | xargs -I {} delete {} --force
```

## Shell Customization

### Shell Aliases (in your ~/.bashrc or ~/.zshrc)

```bash
# Quick Keep shell access
alias ks='keep shell'
alias ksp='keep shell --stage=production'
alias ksl='keep shell --stage=local'

# Direct commands
alias kget='keep get'
alias kset='keep set'
alias klist='keep list'
```

### Custom Prompts

The shell respects the `KEEP_PROMPT` environment variable:
```bash
# Custom prompt format
export KEEP_PROMPT="[%vault%:%stage%] → "

# Minimal prompt
export KEEP_PROMPT="> "

# With colors (using ANSI codes)
export KEEP_PROMPT="\033[32m%vault%\033[0m:\033[33m%stage%\033[0m> "
```

## Performance Tips

### Reduce API Calls
```bash
# Instead of multiple gets:
>>> get API_KEY
>>> get API_SECRET
>>> get API_URL

# Use list with pattern:
>>> list API_*
```

### Cache Context
```bash
# Set your common context at start
>>> production && ssm

# Now all commands use this context
>>> list
>>> get API_KEY
>>> set NEW_SECRET
```

### Batch Operations
```bash
# Import all at once
>>> import large-file.env

# Instead of individual copies
>>> copy * staging  # Copies all secrets in one operation
```
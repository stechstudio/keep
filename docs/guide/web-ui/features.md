# Web UI Features

## Secret Management

The Secrets page provides full CRUD operations with real-time search and filtering. Select your vault and env from the dropdowns, then manage secrets through the table interface. Values are masked by default for security.

**Key capabilities:**
- Create, edit, rename, and delete secrets
- Copy secrets between environments and vaults
- View revision history
- Bulk import from `.env` files
- Export in ENV, JSON, YAML, or Shell format

## Diff Matrix

The Diff view displays a comparison matrix of secrets across multiple vault/env combinations. Use it to:

- Identify missing secrets (empty cells)
- Spot value differences (highlighted cells)  
- Promote secrets between environments
- Audit configuration drift
- Export comparisons for reporting

Select which combinations to compare using the toggle controls. Click any cell to create or edit secrets directly from the diff view.

## Template Management

Templates define your application's complete configuration with placeholders for secrets:

```env
APP_NAME=MyApp
APP_ENV=production
DATABASE_URL={ssm:DATABASE_URL}
API_KEY={secretsmanager:API_KEY}
```

**Features:**
- Create templates from existing secrets
- Syntax highlighting and validation
- Process templates for any env
- Mix static values with secret placeholders

## Import & Export

### Import
Drop `.env` files or paste content to bulk import secrets. The preview shows what will be imported with conflict detection. Choose to skip existing secrets or overwrite them.

### Export  
Generate configuration files in multiple formats:
- **ENV** - Standard `.env` format
- **JSON** - Structured object
- **YAML** - Key-value pairs
- **Shell** - Export statements

Export supports filtering by patterns and selective inclusion.

## Settings & Configuration

The Settings page provides visual configuration for:

- **Vaults** - Add, edit, or remove vault configurations
- **Envs** - Manage environment envs
- **Workspace** - Filter which vaults/envs appear (personal preference)
- **Permissions** - Test vault access and operations

## Keyboard Shortcuts

- `/` - Focus search
- `Esc` - Close dialogs  
- `Enter` - Confirm actions

## Best Practices

- Always verify you're in the correct vault/env before making changes
- Use the diff view before promoting between environments
- Keep values masked unless actively needed
- Export regularly for backup but never commit to version control
- Stop the server when not in use to expire the auth token

## Security Considerations

The Web UI operates with the same AWS IAM permissions as your CLI. All operations go directly to AWS services through the Keep backend. No secrets are cached locally, and all values are fetched fresh on each page load.

For production use, ensure:
- Never expose the server to public internet
- Use SSH tunneling for remote access
- Review IAM policies to restrict access appropriately
- Monitor CloudTrail for audit logging
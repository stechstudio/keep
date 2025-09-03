# Managing Secrets in the Web UI

The Secrets page provides a comprehensive interface for managing individual secrets with real-time search, filtering, and inline editing.

## Viewing Secrets

### Vault and Stage Selection
Use the dropdowns at the top to select:
- **Vault** - Which vault to connect to (AWS SSM, Secrets Manager, etc.)
- **Stage** - Which environment to view (local, staging, production)

The UI remembers your selection between sessions.

### Search and Filter
- **Real-time search** - Type to instantly filter secrets
- **Key or value search** - Matches both secret names and values
- **Case-insensitive** - Search is not case-sensitive

### Table Features
- **Sortable columns** - Click headers to sort
- **Masked values** - Click the eye icon to reveal individual values
- **Last modified** - Shows relative time ("2 hours ago") or actual date
- **Actions menu** - Quick access to all operations

## Creating Secrets

Click **Add Secret** to open the creation dialog:

1. **Enter key name** - Must be unique in the current vault/stage
2. **Enter value** - Supports multi-line values
3. **Save** - Creates immediately in the vault

Naming conventions:
- Use UPPER_SNAKE_CASE for environment variables
- Use forward slashes for hierarchical organization (AWS SSM)
- Avoid special characters that might cause issues

## Editing Secrets

Click **Edit** from the actions menu:

1. **Modify value** - Key cannot be changed (use rename instead)
2. **Preview changes** - See the current value for comparison
3. **Save** - Updates immediately in the vault

## Additional Operations

### Rename
- Changes the key while preserving the value
- Creates new secret and deletes old one
- Updates all references if using templates

### Copy to Stage
- Copies secret to a different environment
- Select target vault and stage
- Useful for promoting from staging to production

### View History
- Shows all versions of a secret
- Includes who made changes and when
- Ability to restore previous versions

### Delete
- Permanently removes the secret
- Requires confirmation
- Cannot be undone

## Bulk Operations

### Import from File
Click the **Import** button to:
1. Upload or drag-drop a `.env` file
2. Preview what will be imported
3. Choose conflict resolution strategy
4. Apply changes in bulk

### Export Selected
- Select multiple secrets with checkboxes
- Export in various formats (ENV, JSON, YAML)
- Download or copy to clipboard

## Tips

### Keyboard Shortcuts
- `/` - Focus search field
- `Esc` - Close dialogs
- `Enter` - Confirm actions

### Performance
- Use search to filter large lists
- The UI fetches fresh data on each page load
- No caching ensures you see current values

### Best Practices
- Always verify the correct vault/stage before making changes
- Use the diff view to compare before copying between stages
- Take advantage of history for auditing changes
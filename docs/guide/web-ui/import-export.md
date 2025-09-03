# Import & Export

The Web UI provides powerful tools for bulk import and export operations with visual feedback and validation.

## Import Wizard

The three-step import wizard guides you through importing `.env` files safely.

### Step 1: Upload File

**Drag and Drop**
- Drag `.env` file onto the upload area
- Visual feedback during drag
- Instant file validation

**Browse**
- Click to open file picker
- Select `.env` or `.txt` files
- Multiple file support (coming soon)

**Paste Content**
- Switch to "Paste" tab
- Paste environment variables directly
- Useful for snippets or CI/CD

### Step 2: Preview & Configure

**Preview Table**
Shows what will be imported:
- **Key** - Variable name
- **New Value** - Value to be imported
- **Current** - Existing value (if any)
- **Status** - New, Update, or Conflict

**Conflict Resolution**
Choose how to handle existing keys:
- **Skip existing** - Only add new secrets
- **Overwrite all** - Replace existing values

**Filtering Options**
- **Only** - Comma-separated patterns to include
- **Except** - Comma-separated patterns to exclude

Example:
```
Only: DB_*, API_*
Except: *_TEST, *_LOCAL
```

### Step 3: Results

After import completion:
- **Success count** - Secrets imported successfully
- **Skipped count** - Existing secrets skipped
- **Failed count** - Import errors
- **Detailed list** - Each secret with its result

## Export Features

### Quick Export

From the Secrets page:
1. Click **Export** button
2. Select format (ENV, JSON, YAML, Shell)
3. Choose download or copy to clipboard

### Export Page

Dedicated export interface with:

**Format Options**
- **ENV** - Standard `.env` format
- **JSON** - Structured JSON object
- **YAML** - YAML key-value pairs
- **Shell** - Shell export statements

**Live Preview**
- See exactly what will be exported
- Syntax highlighting
- Real-time format switching

**Export Actions**
- **Download** - Save as file
- **Copy** - Copy to clipboard
- **Email** - Send via email (coming soon)

### Selective Export

Choose what to export:
- Current vault/stage only
- Filtered results from search
- Hand-picked secrets (checkbox selection)

## Format Examples

### ENV Format
```env
DATABASE_URL=postgresql://localhost/app
API_KEY=sk_live_abc123
DEBUG=false
```

### JSON Format
```json
{
  "DATABASE_URL": "postgresql://localhost/app",
  "API_KEY": "sk_live_abc123",
  "DEBUG": "false"
}
```

### YAML Format
```yaml
DATABASE_URL: postgresql://localhost/app
API_KEY: sk_live_abc123
DEBUG: false
```

### Shell Format
```bash
export DATABASE_URL="postgresql://localhost/app"
export API_KEY="sk_live_abc123"
export DEBUG="false"
```

## Best Practices

### Import Safety
1. **Always preview** before importing
2. **Use dry run** for testing
3. **Backup first** before overwriting
4. **Check vault/stage** is correct

### Export Security
1. **Never commit** exported files to git
2. **Use secure channels** for sharing
3. **Rotate after sharing** sensitive values
4. **Mask values** when possible

### Bulk Operations

**Migration Workflow**
1. Export from source environment
2. Review and clean the export
3. Import to target environment
4. Verify with diff view

**Backup Strategy**
1. Regular exports for disaster recovery
2. Version control the structure, not values
3. Document export schedules
4. Test restore procedures

## Troubleshooting

### Import Issues

**"Invalid file format"**
- Ensure proper `.env` syntax
- Remove comments and empty lines
- Check for special characters

**"Conflicts detected"**
- Review conflict resolution setting
- Consider skip vs overwrite
- Use filters to be selective

### Export Issues

**"No secrets to export"**
- Check vault/stage selection
- Verify permissions
- Clear search filters

**Large Exports**
- Consider pagination
- Export in batches
- Use filtering to reduce size
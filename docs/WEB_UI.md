# Keep Web UI Guide

## Overview

Keep includes a modern web-based interface for managing secrets visually. The UI runs locally on your machine and connects directly to your configured vaults.

## Starting the Server

```bash
# Start with defaults (port 4000, opens browser)
keep server

# Custom port
keep server --port=8080

# Without auto-opening browser
keep server --no-browser

# Specific network interface (default: 127.0.0.1)
keep server --host=0.0.0.0
```

The server generates a secure token on startup and automatically injects it into the browser session. This ensures only your browser can access the API.

## Features

### Secrets Management

The main Secrets view provides:
- **Table view** of all secrets with key, value (masked), and modification date
- **Search** with real-time filtering
- **Quick actions** menu for each secret:
  - Edit value
  - Rename key
  - Copy to different stage
  - View history
  - Delete with confirmation
- **Vault/Stage selector** to switch contexts
- **Import wizard** for bulk importing from .env files

### Diff View

Compare secrets across stages and vaults:
- **Matrix view** showing all secrets across selected environments
- **Visual indicators**:
  - ✓ Present and matching
  - ⚠️ Present but different
  - ✗ Missing
- **Inline editing** directly from diff cells
- **Multi-select** for comparing specific vault/stage combinations
- **Export diff** to CSV for reporting

### Export

Export secrets in multiple formats:
- **Formats**: ENV, JSON, YAML, Shell script
- **Live preview** before download
- **Copy to clipboard** for quick use
- **Template support** for custom formats

### Import Wizard

Three-step process for importing .env files:
1. **Upload** - Drag & drop or browse for file
2. **Preview** - Review changes with conflict detection
3. **Execute** - Apply changes with detailed results

Features:
- **Conflict resolution**:
  - Skip existing keys
  - Overwrite all
- **Filtering** with only/except patterns
- **Dry run mode** to preview without changes
- **Detailed results** showing success/skip/fail counts

### Settings

Manage Keep configuration:

#### General Settings
- Application name
- Secret namespace prefix
- Default vault and stage

#### Vaults Management
- Add new vault configurations
- Edit existing vault settings
- Test vault permissions
- Delete unused vaults

#### Stages Management
- Add custom stages
- Remove stages (except system defaults)
- Reorder stage priority

## Navigation

### URL Routes
- `/` - Secrets table (default)
- `/diff` - Diff matrix view
- `/export` - Export interface
- `/settings` - Configuration

### Keyboard Shortcuts
- `/` - Focus search field
- `Esc` - Close dialogs
- `Enter` - Confirm actions
- `Cmd/Ctrl + K` - Quick command palette (coming soon)

## Security

### Authentication
- Token-based authentication generated per session
- Token automatically injected into browser
- No persistent sessions or cookies
- Localhost-only by default

### Data Protection
- Values masked by default
- Unmask on-demand per secret
- No data stored locally
- All operations go directly to vault

### Network Security
- Binds to 127.0.0.1 by default
- No CORS headers (prevents external access)
- HTTPS not required for localhost
- Rate limiting on API endpoints

## Troubleshooting

### Port Already in Use
```bash
# Check what's using port 4000
lsof -i :4000

# Use a different port
keep server --port=8080
```

### Browser Doesn't Open
```bash
# Manual URL
http://localhost:4000

# Or disable auto-open
keep server --no-browser
```

### Authentication Errors
If you see "Unauthorized" errors:
1. Restart the server (generates new token)
2. Refresh the browser (gets new token)
3. Clear browser cache if issues persist

### Vault Access Issues
- Check AWS credentials are configured
- Verify IAM permissions for the vault
- Use Settings → Vaults → Verify to test access
- Check error logs in terminal

### Build Issues
```bash
# Rebuild UI assets
composer ui:build

# Clean and rebuild
composer ui:clean
composer build
```

## Performance

### Optimization Tips
- Use search to filter large secret lists
- Select specific vault/stage combinations in diff view
- Export filtered results instead of all secrets
- Close unused browser tabs (each maintains connection)

### Resource Usage
- Minimal CPU usage (event-driven)
- Memory: ~50MB for PHP server
- Network: Local only, no external calls
- Disk: No persistent storage

## Browser Support

### Recommended
- Chrome/Edge 90+
- Firefox 88+
- Safari 14+

### Features by Browser
- **Chrome/Edge**: Full support including clipboard API
- **Firefox**: Full support, may prompt for clipboard access
- **Safari**: Full support, clipboard requires user interaction

## Development

### Building from Source
```bash
# Install dependencies
composer install
cd src/Server/frontend
npm install

# Build for production
npm run build

# Development with hot reload
npm run dev
```

### Running Tests
```bash
# PHP tests
composer test

# JavaScript tests
cd src/Server/frontend
npm test

# With coverage
npm run test:coverage
```

### Project Structure
```
src/Server/
├── server.php           # Main entry point
├── Router.php           # Request routing
├── Controllers/         # API endpoints
├── frontend/           # Vue.js application
│   ├── src/
│   │   ├── components/  # Vue components
│   │   ├── composables/ # Shared logic
│   │   ├── services/    # API client
│   │   └── utils/       # Helpers
│   └── public/         # Built assets
└── BUILD.md            # Build documentation
```

## Contributing

See [CONTRIBUTING.md](../CONTRIBUTING.md) for development guidelines.

## FAQ

**Q: Can I access the UI remotely?**
A: By default, no. The server binds to localhost only. For remote access, use SSH tunneling.

**Q: Is the UI data encrypted?**
A: Data is encrypted in transit to vaults (HTTPS to AWS). Local communication is unencrypted but localhost-only.

**Q: Can multiple users access simultaneously?**
A: Yes, but each session gets its own token. Changes are not synchronized in real-time.

**Q: How do I customize the UI theme?**
A: Currently uses a dark theme. Light theme support planned for future release.

**Q: Can I export the UI settings?**
A: Settings are stored in Keep's configuration files, not the UI. Use `keep configure` to manage.

## Changelog

See [CHANGELOG.md](../CHANGELOG.md) for version history and updates.
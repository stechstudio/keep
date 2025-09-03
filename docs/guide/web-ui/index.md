# Web UI Getting Started

Keep includes a modern web interface for visual secret management. The UI runs locally on your machine and provides a rich, interactive alternative to the command line.

## Starting the Server

```bash
# Start with defaults (port 4000, opens browser automatically)
keep server

# Custom port
keep server --port=8080

# Without auto-opening browser
keep server --no-browser
```

When the server starts:
1. Generates a secure authentication token
2. Launches your default browser
3. Automatically logs you in with the token
4. Provides full access to your configured vaults

## Key Features

### Visual Secret Management
- Table view with search and filtering
- Inline editing with validation
- Masked values with toggle visibility
- Quick actions menu for each secret

### Diff Matrix
- Compare secrets across stages and vaults
- Visual indicators for differences
- Edit directly from the diff view
- Export comparison results

### Import Wizard
- Drag-and-drop `.env` files
- Preview changes before applying
- Conflict resolution options
- Detailed import results

### Settings Management
- Configure vaults visually
- Add/remove stages
- Test vault permissions
- Update application settings

## Navigation

The Web UI is organized into four main sections:

- **Secrets** - Main table view for managing individual secrets
- **Diff** - Matrix view for comparing across environments
- **Export** - Generate configuration files in various formats
- **Settings** - Configure vaults, stages, and application preferences

## Security Model

The Web UI prioritizes security:

- **Local only** - Server binds to 127.0.0.1 by default
- **Session tokens** - Unique token generated per session
- **No persistence** - No data stored locally, all operations go directly to vaults
- **Masked values** - Secrets are masked by default, unmask on-demand
- **Secure shutdown** - Token expires when server stops

## Next Steps

- [Managing Secrets](./managing-secrets) - Learn the UI workflows
- [Diff & Compare](./diff-compare) - Master the diff matrix
- [Import & Export](./import-export) - Bulk operations guide
- [Security Details](./security) - Understanding the security model
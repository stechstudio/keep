# Web UI

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

The server:
- Generates a secure authentication token
- Opens your browser and logs you in automatically
- Binds to localhost only (no external access by default)
- Expires the token when stopped

## Interface Overview

The Web UI provides five main sections:

- **Secrets** - Manage individual secrets with search, filtering, and inline editing
- **Templates** - Create and manage configuration templates with placeholders
- **Diff** - Compare secrets across envs and vaults in a matrix view
- **Export** - Generate configuration files in various formats
- **Settings** - Configure vaults, envs, workspace, and preferences

## Security

- **Local only** - Server binds to 127.0.0.1, no external access
- **Token auth** - Unique token per session, expires on shutdown
- **No persistence** - Direct vault operations, no local secret storage
- **Value masking** - Secrets masked by default with on-demand reveal

For remote access, use SSH tunneling:
```bash
ssh -L 4000:localhost:4000 user@server
```

## Next: [Features & Capabilities](./features)
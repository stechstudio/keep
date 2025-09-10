# keep server

Start the Keep web UI server for visual secret management.

## Synopsis

```bash
keep server [options]
```

## Description

Launches a local web server that provides a browser-based interface for managing secrets. The server runs on your machine and connects directly to your configured vaults.

## Options

### `--port=<port>`
- **Default**: `4000`
- **Description**: TCP port for the web server
- **Example**: `keep server --port=8080`

### `--host=<host>`
- **Default**: `127.0.0.1`
- **Description**: Network interface to bind to
- **Security**: Use `127.0.0.1` for local-only access
- **Example**: `keep server --host=0.0.0.0` (allows network access)

### `--no-browser`
- **Default**: Opens browser automatically
- **Description**: Prevent automatic browser launch
- **Example**: `keep server --no-browser`

## Examples

### Basic Usage
```bash
# Start server with defaults
keep server
```
Output:
```
Starting Keep Web UI server...
✓ Server running at http://localhost:4000
✓ Browser opened automatically
Press Ctrl+C to stop
```

### Custom Port
```bash
# Use port 8080 instead of default 4000
keep server --port=8080
```

### Headless Mode
```bash
# Start without opening browser
keep server --no-browser
```

### Network Access
```bash
# Allow access from network (use with caution)
keep server --host=0.0.0.0 --port=8080
```

## Security

### Authentication
The server generates a unique authentication token on each startup:
- Token is automatically injected into the browser session
- No manual authentication required for local access
- Token expires when server stops

### Network Security
- **Default binding**: `127.0.0.1` (localhost only)
- **No HTTPS**: Not required for localhost connections
- **No CORS**: Prevents cross-origin requests
- **Token validation**: All API requests require valid token

### Best Practices
1. Always use localhost binding for security
2. Don't expose the server to public networks
3. Stop the server when not in use
4. Use SSH tunneling for remote access if needed

## Troubleshooting

### Port Already in Use
```bash
Error: Port 4000 is already in use
```
**Solution**: Use a different port with `--port` option

### Permission Denied
```bash
Error: Permission denied binding to port 80
```
**Solution**: Use a port above 1024, or run with appropriate permissions

### Browser Doesn't Open
**Solution**: 
1. Check if browser is installed
2. Open manually: `http://localhost:4000`
3. Use `--no-browser` to suppress auto-open

### Connection Refused
**Solution**:
1. Verify server is running
2. Check firewall settings
3. Ensure correct port and host

## Environment Variables

### `KEEP_SERVER_PORT`
Override default port:
```bash
export KEEP_SERVER_PORT=8080
keep server
```

### `KEEP_SERVER_HOST`
Override default host:
```bash
export KEEP_SERVER_HOST=0.0.0.0
keep server
```

### `KEEP_NO_BROWSER`
Disable browser auto-open:
```bash
export KEEP_NO_BROWSER=1
keep server
```

## Files

### Server Files
- `src/Server/server.php` - Main server router
- `src/Server/public/` - Static assets
- `src/Server/Controllers/` - API endpoints

### No Persistent Storage
The web UI does not create or modify any local files. All data is read from and written to configured vaults.

## Related Commands

- [`keep init`](INIT.md) - Set up vaults before using UI
- [`keep list`](LIST.md) - CLI alternative to view secrets
- [`keep export`](EXPORT.md) - CLI alternative for exporting

## Notes

- The server is intended for local development use
- Each server instance generates a unique auth token
- Multiple instances can run on different ports
- Changes made in UI are immediately reflected in vaults
- No caching - always shows current vault state

## Exit Codes

- `0` - Server stopped normally
- `1` - Port binding failed
- `2` - Invalid configuration
- `130` - Interrupted (Ctrl+C)
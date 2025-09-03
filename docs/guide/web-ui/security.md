# Web UI Security

The Keep Web UI is designed with security as the top priority. This document explains the security model and best practices.

## Authentication Model

### Token-Based Authentication
When you start the server with `keep server`:

1. **Token Generation** - A cryptographically secure random token is generated
2. **Token Injection** - The token is automatically injected into your browser session
3. **Automatic Login** - No manual authentication required for local access
4. **Session Scope** - Token is valid only for the current server session

```bash
# The server generates output like:
Starting Keep Web UI server...
Auth token: a1b2c3d4e5f6... (automatically injected)
Server running at http://localhost:4000
```

### Token Lifecycle
- **Created** when server starts
- **Injected** into browser on first page load
- **Validated** on every API request
- **Expires** when server stops

## Network Security

### Localhost-Only by Default
```bash
# Default: Binds to localhost only
keep server  # Listens on 127.0.0.1:4000

# Explicit binding (use with caution)
keep server --host=0.0.0.0  # Allows network access
```

### No HTTPS Required
- Localhost connections don't require HTTPS
- Browser treats localhost as a secure context
- No self-signed certificate warnings

### CORS Protection
- No Cross-Origin Resource Sharing headers
- Prevents requests from external domains
- API only responds to same-origin requests

## Data Protection

### Value Masking
All secret values are masked by default:
- Display as `••••` in the UI
- Unmask individually with eye icon
- Actual values never logged
- Masks reapply on page refresh

### No Local Storage
The Web UI stores minimal data locally:
- **Preferences** - Selected vault/stage (not secret)
- **No secrets** - Values never stored in browser
- **No cache** - Always fetches fresh from vault

### Secure Communication
All vault operations use the Keep backend:
```
Browser → Keep Server → AWS SDK → AWS Services
         ↑              ↑           ↑
      localhost     IAM auth    HTTPS/TLS
```

## Access Control

### IAM-Based Permissions
The Web UI respects all AWS IAM policies:
- Read/write permissions per vault
- Stage-based access control
- No privilege escalation

### Verification
Test vault permissions from Settings:
```
Settings → Vaults → Verify Permissions
```

Shows exactly what operations are allowed:
- ✓ Read
- ✓ Write  
- ✓ List
- ✗ Delete (if not permitted)

## Best Practices

### Secure Usage
1. **Local Development Only** - Don't expose to public internet
2. **Stop When Done** - Shut down server when not in use
3. **Verify Vault/Stage** - Always check before modifying
4. **Use Masking** - Keep values masked unless needed

### Remote Access
If remote access is required:

**Option 1: SSH Tunnel (Recommended)**
```bash
# On remote server
keep server --port=4000

# On local machine
ssh -L 4000:localhost:4000 user@server
# Access at http://localhost:4000
```

**Option 2: VPN**
- Connect via VPN first
- Then access internal IP

**Never:**
- Expose directly to internet
- Use `--host=0.0.0.0` on public servers
- Share authentication tokens

### Audit Trail
While the UI doesn't log operations, AWS services do:
- CloudTrail logs all API calls
- Parameter Store history (if versioning enabled)
- Secrets Manager automatic versioning

## Security FAQ

**Q: Is the connection encrypted?**
A: Local connections (localhost) don't need encryption. AWS API calls use TLS.

**Q: Can multiple users access simultaneously?**
A: Yes, each browser session gets the same token. Consider this when sharing access.

**Q: What if someone gets the token?**
A: Tokens expire when the server stops. Always shut down when finished.

**Q: Are secrets encrypted at rest?**
A: Yes, by AWS (SSM and Secrets Manager both encrypt at rest).

**Q: Can I restrict UI features?**
A: The UI respects IAM permissions. Restrict access at the AWS level.

## Incident Response

If you suspect unauthorized access:

1. **Stop the server immediately** (`Ctrl+C`)
2. **Rotate compromised secrets** in AWS
3. **Review CloudTrail logs** for unauthorized API calls
4. **Update IAM policies** if needed
5. **Report to your security team**
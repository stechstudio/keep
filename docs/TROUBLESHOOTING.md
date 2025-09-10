# Keep Web UI Troubleshooting Guide

## Common Issues

### Server Won't Start

#### Port Already in Use
**Error**: `Address already in use: 4000`

**Solutions**:
```bash
# Find what's using the port
lsof -i :4000

# Kill the process
kill -9 <PID>

# Or use a different port
keep server --port=8080
```

#### Permission Denied
**Error**: `Permission denied: Cannot bind to port 80`

**Solutions**:
- Use a port above 1024 (no special permissions required)
- Default port 4000 works without sudo

### Browser Issues

#### Page Won't Load
**Check**:
1. Server is running (check terminal)
2. Correct URL: `http://localhost:4000`
3. No HTTPS - use `http://` not `https://`

**Solutions**:
```bash
# Verify server is listening
curl http://localhost:4000

# Try different browser
# Clear browser cache
# Disable browser extensions
```

#### Authentication Errors
**Error**: "Unauthorized" or "Invalid token"

**Solutions**:
1. Refresh the page (F5)
2. Restart the server
3. Clear browser localStorage:
```javascript
// In browser console
localStorage.clear()
location.reload()
```

### Vault Connection Issues

#### No Secrets Showing
**Check**:
- Correct vault selected
- AWS credentials configured
- IAM permissions granted

**Debug**:
```bash
# Test vault access via CLI
keep list --vault=aws --env=production

# Check AWS credentials
aws sts get-caller-identity
```

#### Slow Loading
**Causes**:
- Large number of secrets
- Network latency to AWS
- Rate limiting

**Solutions**:
- Use search to filter results
- Paginate large lists (future feature)
- Check AWS service health

### Data Issues

#### Values Not Updating
**Check**:
- Changes saved successfully (check for errors)
- Correct vault/env selected
- No caching (refresh page)

**Debug**:
```bash
# Verify via CLI
keep get MY_SECRET --vault=aws --env=prod
```

#### Masked Values Won't Unmask
**Solutions**:
- Click the eye icon per secret
- Check browser console for errors
- Ensure sufficient vault permissions

### Import/Export Problems

#### Import Fails
**Common Causes**:
- Invalid file format
- Special characters in keys
- File too large

**Solutions**:
- Validate .env file syntax
- Remove comments and empty lines
- Use smaller batches

#### Export Not Working
**Check**:
- Browser download permissions
- Popup blocker settings
- Sufficient disk space

### Build Issues

#### Assets Not Loading
**Error**: 404 for JS/CSS files

**Solutions**:
```bash
# Rebuild assets
cd src/Server/frontend
npm run build

# Clear and rebuild
npm run clean
npm run build
```

#### Development Mode Issues
**Check**:
- Vite dev server running
- Correct proxy configuration
- HMR port available (5173)

```bash
# Start dev server
cd src/Server/frontend
npm run dev
```

## Debug Mode

### Enable Verbose Logging
```bash
# Set debug environment variable
DEBUG=1 keep server
```

### Check Browser Console
1. Open DevTools (F12)
2. Check Console tab for errors
3. Check Network tab for failed requests

### API Testing
```bash
# Test API directly
curl -H "X-Auth-Token: <token>" \
     http://localhost:4000/api/secrets?vault=aws&env=local
```

## Performance Issues

### Slow Response Times
**Optimize**:
- Reduce number of secrets per vault
- Use specific vault/env combinations
- Close unused browser tabs

### High Memory Usage
**Check**:
```bash
# Monitor PHP process
top -p $(pgrep -f "php.*server.php")
```

**Solutions**:
- Restart server periodically
- Limit concurrent connections
- Use production build (not dev)

## Error Messages

### "Could not access vault"
- Check vault configuration
- Verify AWS credentials
- Test with `keep verify`

### "Secret not found"
- Verify correct vault/env
- Check key exists
- Ensure proper permissions

### "Failed to save secret"
- Check write permissions
- Verify vault isn't read-only
- Check for special characters

### "Network error"
- Server still running?
- Firewall blocking connection?
- Browser offline mode?

## Getting Help

### Collect Debug Information
```bash
# Version info
keep --version
php --version
node --version

# Check configuration
keep info

# Test vault access
keep verify
```

### Log Files
The web UI doesn't create log files by default. To capture logs:
```bash
# Redirect output to file
keep server 2>&1 | tee keep-server.log
```

### Reporting Issues
When reporting issues, include:
1. Error messages from browser console
2. Server output from terminal
3. Keep version (`keep --version`)
4. Browser and OS information
5. Steps to reproduce

## Quick Fixes

### Complete Reset
```bash
# Stop server
Ctrl+C

# Clear browser data
# In browser: Settings → Clear browsing data

# Rebuild UI
composer ui:clean
composer ui:build

# Restart server
keep server
```

### Emergency Access
If UI is completely broken:
```bash
# Use CLI instead
keep list
keep get SECRET_NAME
keep set SECRET_NAME value

# Or use AWS CLI directly
aws ssm get-parameter --name /myapp/SECRET_NAME
aws secretsmanager get-secret-value --secret-id SECRET_NAME
```

## Known Limitations

- No real-time sync between multiple sessions
- Large files (>5MB) may fail to import
- Some special characters not supported in keys
- History limited by vault capabilities
- No offline mode - requires vault connection
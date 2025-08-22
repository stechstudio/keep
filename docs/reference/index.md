# CLI Reference

Keep provides a comprehensive command-line interface for managing secrets across different environments and storage backends.

## Global Options

All Keep commands support these global options:

- `--help, -h`: Show command help
- `--quiet, -q`: Suppress output messages
- `--verbose, -v`: Increase verbosity of output

## Command Categories

### Configuration Commands
- [`configure`](./commands/configure) - Initialize Keep configuration in a project
- [`vault:add`](./commands/vault-add) - Add a new vault configuration
- [`vault:list`](./commands/vault-list) - List configured vaults

### Secret Management Commands
- [`set`](./commands/set) - Set a secret value
- [`get`](./commands/get) - Retrieve a secret value
- [`list`](./commands/list) - List all secrets in a context
- [`delete`](./commands/delete) - Delete a secret
- [`copy`](./commands/copy) - Copy secrets between contexts

### Import/Export Commands  
- [`export`](./commands/export) - Export secrets in various formats
- [`import`](./commands/import) - Import secrets from files
- [`cache`](./commands/cache) - Cache secrets for Laravel integration

### Utility Commands
- [`diff`](./commands/diff) - Compare secrets between contexts
- [`merge`](./commands/merge) - Merge secrets from multiple contexts
- [`info`](./commands/info) - Show information about contexts and secrets

## Common Usage Patterns

### Working with Contexts
Most commands accept a context in the format `vault:stage`:

```bash
keep set myapp:development API_KEY "dev-key-123"
keep get myapp:production API_KEY
keep list myapp:staging
```

### Using Default Context
If you don't specify a vault, Keep will use the default vault configured for your project:

```bash
keep set development API_KEY "dev-key-123"  # Uses default vault
```

### Command Aliases
Many commands have shorter aliases for convenience:

- `list` → `ls`
- `export` → `ex` 
- `import` → `im`

Navigate to individual command pages for detailed usage examples and options.
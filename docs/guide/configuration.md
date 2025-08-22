# Configuration

Before you can start managing secrets with Keep, you need to configure it for your project. This involves setting up the basic project structure and adding your first vault.

## Project Initialization

Navigate to your project directory and initialize Keep:

```bash
cd /path/to/your/project
keep configure
```

This command will:
- Create a `.keep/` directory in your project root
- Generate a `settings.json` file with project configuration
- Set up the basic directory structure for vault configurations

You'll be prompted for:
- **Project name**: A friendly name for your project
- **Namespace**: A unique identifier (typically a slug of your project name) used as the prefix for secrets
- **Stages**: Environment names (e.g., `development`, `staging`, `production`)

## Configuration Structure

After initialization, your project will have this structure:

```
your-project/
├── .keep/
│   ├── settings.json
│   └── vaults/
└── (your project files)
```

### settings.json

The settings file contains your project configuration:

```json
{
  "app_name": "My Application",
  "namespace": "myapp",
  "stages": ["development", "staging", "production"],
  "default_vault": "local",
  "version": "1.0",
  "created_at": "2025-01-01T12:00:00+00:00",
  "updated_at": "2025-01-01T12:00:00+00:00"
}
```

## Adding Your First Vault

After initialization, add a vault to store your secrets:

```bash
# Add a local vault for development
keep vault:add local myapp

# Or add an AWS SSM vault for production
keep vault:add aws-ssm production-vault \
  --region=us-east-1 \
  --prefix="/myapp/"
```

## Environment Variables

Keep can use environment variables for configuration:

- `KEEP_DEFAULT_VAULT`: Override the default vault
- `KEEP_CONFIG_PATH`: Custom path to `.keep` directory
- `AWS_PROFILE`: AWS profile for AWS vault drivers
- `AWS_REGION`: AWS region for AWS vault drivers

## Laravel Integration

For Laravel projects, Keep can integrate with your application's configuration:

```bash
keep configure --laravel
```

This will:
- Publish the Keep configuration file to `config/keep.php`
- Set up the service provider for helper function access
- Configure caching for production use

## Next Steps

With Keep configured, you're ready to start managing secrets. Learn how to [get started with basic operations](./quick-start) or explore [vault types](./vaults) in detail.
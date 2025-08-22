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
keep vault:add
```

## Verify Configuration and Vault Access

Once you've added a vault, verify that everything is set up correctly:

```bash
keep verify
```

This will check your vault permissions across all stages and ensure that Keep can access and manage secrets as expected.

## Next Steps

With Keep configured, you're ready to start managing secrets. Learn how to [get started with basic operations](./quick-start) or explore [vault types](./vaults) in detail.
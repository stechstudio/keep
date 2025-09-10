# Configuration

## Initialize Your Project

Navigate to your project and run:

```bash
keep init
```

This interactive command will:
1. Create a `.keep/` directory with your project configuration
2. Optionally set up your first vault (with automatic permission testing)
3. Initialize your workspace to show all vaults and envs by default

You'll be prompted for:
- **Project name**: Display name for your project
- **Namespace**: Unique identifier for secret prefixes
- **Environments**: Environment names (defaults to local, staging, production)

## Project Structure

```
your-project/
├── .keep/
│   ├── settings.json      # Project configuration (versioned)
│   ├── vaults/            # Vault configurations (versioned)
│   └── local/             # Personal workspace (not versioned)
│       └── workspace.json
└── ...
```

### Project Settings

The `settings.json` file contains shared project configuration:

```json
{
  "app_name": "My Application",
  "namespace": "myapp",
  "envs": ["local", "staging", "production"],
  "default_vault": "aws-ssm"
}
```

### Workspace Settings

The `local/workspace.json` file contains your personal workspace preferences:

```json
{
  "active_vaults": ["ssm", "secretsmanager"],
  "active_envs": ["local", "staging"]
}
```

These settings:
- Are **personal to you** and not committed to version control
- Filter which vaults and envs appear in commands and UI
- Help focus on the environments you actively work with
- Don't affect other team members' configurations

## Managing Workspaces

By default, Keep shows **all configured vaults and envs** - no filtering is applied. This ensures you have immediate access to everything in your project.

### Configure Your Workspace (Optional)

If you want to filter which vaults and envs appear in commands and the Web UI:

```bash
keep workspace
```

This is useful when:
- You only work with certain environments (e.g., dev and staging, not production)
- Your team has multiple vaults but you only need access to some
- You want to reduce clutter in command outputs and the Web UI

**Note:** Workspace filtering is purely cosmetic - it doesn't affect permissions or access, just what's displayed

### Why Use Workspace Filtering?

In larger teams, you might have:
- Multiple vaults (e.g., `payments`, `api`, `frontend`)
- Many envs (e.g., `local`, `dev`, `qa`, `staging`, `production`)
- Different team members working with different subsets

Workspace filtering lets each developer see only what's relevant to them, while the full configuration remains available to the team.

## Managing Environments

The default envs are local, staging, and production. You can add custom envs as needed:

```bash
# Add a custom env
keep env:add integration

# Common custom envs
keep env:add qa
keep env:add demo
keep env:add sandbox
```

Custom envs can be used with all commands:
```bash
keep set API_KEY "integration-key" --env=integration
keep copy --only="*" --from=local --to=integration
```

## Add a Vault

```bash
keep vault:add
```

Follow the prompts to configure AWS SSM or Secrets Manager access. After adding a vault, Keep will:
- Automatically test permissions across all environments
- Cache results locally for better performance
- Display which operations (read/write/list) are available

**Note:** AWS credentials must be configured separately from your application secrets. See [AWS Authentication](/guide/aws-authentication) for setup instructions.

## Verify Setup

After configuration, verify your access:

```bash
keep verify
```

This checks:
- Vault connectivity
- AWS credentials
- IAM permissions for each vault/env combination
- Which operations (read/write/list) are available

## Team Collaboration

### What to Commit

✅ **Commit these** (shared team configuration):
- `.keep/settings.json` - Project settings
- `.keep/vaults/*.json` - Vault configurations

❌ **Don't commit these** (personal preferences):
- `.keep/local/*` - Personal workspace settings
- AWS credentials - Use IAM roles or environment variables

The `.keep/.gitignore` file is automatically created to exclude personal files.

### Onboarding New Team Members

1. Clone the repository (includes `.keep/` configuration)
2. Run `keep verify` to check AWS access
3. Run `keep workspace` to personalize their workspace
4. Start working with secrets immediately

## Next Steps

- [Quick Start](./quick-start) - Start managing secrets
- [Vault Configuration](./vaults) - Learn about vault options
- [AWS Authentication](./aws-authentication) - Set up AWS credentials
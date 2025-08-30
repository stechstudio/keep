# Configuration

## Initialize Your Project

Navigate to your project and run:

```bash
keep configure
```

This creates a `.keep/` directory with your project configuration.

You'll be prompted for:
- **Project name**: Display name for your project
- **Namespace**: Unique identifier for secret prefixes
- **Stages**: Environment names (defaults to development, staging, production)

## Project Structure

```
your-project/
├── .keep/
│   ├── settings.json
│   └── vaults/
└── ...
```

The `settings.json` file contains:

```json
{
  "app_name": "My Application",
  "namespace": "myapp",
  "stages": ["development", "staging", "production"],
  "default_vault": "aws-ssm"
}
```

## Managing Stages

The default stages are development, staging, and production. You can add custom stages as needed:

```bash
# Add a custom stage
keep stage:add integration

# Common custom stages
keep stage:add qa
keep stage:add demo
keep stage:add sandbox
```

Custom stages can be used with all commands:
```bash
keep set API_KEY "integration-key" --stage=integration
keep copy --only="*" --from=development --to=integration
```

## Add a Vault

```bash
keep vault:add
```

Follow the prompts to configure AWS SSM or Secrets Manager access.

**Note:** AWS credentials must be configured separately from your application secrets. See [AWS Authentication](/guide/reference/aws-authentication) for setup instructions.

## Verify Setup

```bash
keep verify
```

This checks vault permissions across all stages.

## Next Steps

Start [managing secrets](./quick-start) or explore [vault configuration](./vaults).
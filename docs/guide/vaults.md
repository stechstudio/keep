# Vaults

Vaults are Keep's storage backends for your secrets. Keep currently supports AWS services, with more providers coming soon.

## Available Vault Drivers

### AWS SSM Parameter Store
Hierarchical, cost-effective secret storage using AWS Systems Manager.

**Best for**: Most applications, cost-conscious teams  
**Cost**: Free tier (10,000 parameters), minimal API costs  
**Features**: Path-based organization, KMS encryption, IAM access control  

### AWS Secrets Manager
Premium AWS service with advanced secret management features.

**Best for**: Enterprise applications requiring rotation or cross-region replication  
**Cost**: $0.40/secret/month + API costs  
**Features**: Automatic rotation, native JSON support, cross-region replication  

## Comparison

| Feature | AWS SSM Parameter Store | AWS Secrets Manager |
|---------|------------------------|-------------------|
| **Cost** | Free tier (10,000 params) | $0.40/secret/month |
| **Organization** | Path-based hierarchical | Tag-based with naming |
| **Access Control** | IAM with path patterns | IAM with tags |
| **Automatic Rotation** | No | Yes |
| **Cross-Region** | Manual | Built-in replication |
| **JSON Support** | String values only | Native JSON |
| **Version History** | Yes (Advanced params) | Yes (automatic) |

## Choosing the Right Vault

**AWS SSM Parameter Store**
- ✅ Most applications and environments
- ✅ Cost-sensitive projects (free tier)
- ✅ Simple hierarchical organization
- ❌ No automatic rotation

**AWS Secrets Manager**
- ✅ Database credentials needing rotation
- ✅ Cross-region deployments
- ✅ Complex JSON configurations
- ❌ Higher cost ($0.40/secret/month)

## Multi-Vault Strategies

Use multiple vaults to optimize for cost and features:

```bash
# SSM for most secrets (free tier)
keep vault:add --driver=aws-ssm --name=primary

# Secrets Manager for database credentials (rotation)
keep vault:add --driver=aws-secrets-manager --name=databases
```

Then target specific vaults:
```bash
keep set API_KEY "..." --vault=primary --stage=production
keep set DB_PASSWORD "..." --vault=databases --stage=production
```

## Organization

**Namespace Isolation**
- **SSM**: Path-based (`/myapp/stage/key`)
- **Secrets Manager**: Tag-based with namespace tags

**Stage Separation**
Each vault organizes secrets by stage:
- `development`
- `staging`
- `production`

## Getting Started

```bash
# Add your first vault
keep vault:add

# Verify connectivity
keep verify

# List configured vaults
keep vault:list
```

**Important:** Before adding AWS vaults, ensure your AWS credentials are properly configured. See [AWS Authentication](/guide/aws-authentication) for setup instructions.

## Best Practices

- Start with SSM Parameter Store (free tier)
- Use Secrets Manager only when you need rotation
- Implement least-privilege IAM policies
- Enable CloudTrail logging for audit trails
- Share vault configs via `.keep/` directory

## Next Steps

- **[AWS SSM](./vaults/aws-ssm)** - Configure Parameter Store
- **[AWS Secrets Manager](./vaults/aws-secrets-manager)** - Set up advanced features
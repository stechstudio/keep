# Vaults

Vaults are Keep's storage backends for your secrets. Keep supports multiple vault types, allowing you to choose the right storage solution for each environment and use case.

## Available Vault Drivers

### Local Vault
File-based storage on your local filesystem, perfect for development and testing.

**Best for**: Development, testing, local workflows  
**Security**: Encrypted files on local disk  
**Cost**: Free  
**Scalability**: Single developer/machine  

### AWS SSM Parameter Store
AWS Systems Manager Parameter Store provides hierarchical, cost-effective secret storage.

**Best for**: Production workloads, cost-conscious teams, AWS-native applications  
**Security**: AWS KMS encryption, IAM-based access control  
**Cost**: Free tier (10,000 parameters), minimal API costs  
**Scalability**: Enterprise-grade, unlimited  

### AWS Secrets Manager
Premium AWS service designed specifically for application secrets with advanced features.

**Best for**: Enterprise applications, automatic rotation, cross-region requirements  
**Security**: AWS KMS encryption, advanced IAM policies, automatic rotation  
**Cost**: $0.40/secret/month + API costs  
**Scalability**: Enterprise-grade with cross-region replication  

## Comparison Matrix

| Feature | Local Vault | AWS SSM Parameter Store | AWS Secrets Manager |
|---------|-------------|------------------------|-------------------|
| **Cost** | Free | Free (standard params) | $0.40/secret/month |
| **Setup Complexity** | Minimal | Moderate (IAM setup) | Moderate (IAM setup) |
| **Team Sharing** | Manual file sharing | Native AWS access | Native AWS access |
| **Encryption** | Local file encryption | AWS KMS | AWS KMS |
| **Access Control** | File permissions | IAM path-based | IAM tag-based |
| **Automatic Rotation** | No | No | Yes (supported services) |
| **Cross-Region** | No | Manual setup | Built-in replication |
| **Version History** | Limited | Yes (Advanced params) | Yes (automatic) |
| **JSON Support** | Key-value only | Manual parsing | Native support |
| **CLI Integration** | Direct file access | AWS CLI/SDK | AWS CLI/SDK |
| **Audit Logging** | File system logs | CloudTrail | CloudTrail |
| **High Availability** | Single machine | AWS infrastructure | AWS infrastructure |

## Choosing the Right Vault

### Local Vault
```bash
✅ Perfect for: Development and testing
✅ When: Working solo or small teams
✅ Benefits: Zero setup, no AWS dependency
❌ Limitations: No team sharing, single machine only
```

### AWS SSM Parameter Store  
```bash
✅ Perfect for: Production applications, cost-sensitive projects
✅ When: Using AWS infrastructure, need team collaboration
✅ Benefits: Free tier, familiar hierarchical paths, simple IAM
❌ Limitations: No automatic rotation, manual JSON handling
```

### AWS Secrets Manager
```bash
✅ Perfect for: Enterprise applications, database credentials
✅ When: Need automatic rotation, cross-region deployments
✅ Benefits: Purpose-built for secrets, automatic rotation, JSON support
❌ Limitations: Higher cost, more complex than SSM
```

## Multi-Vault Strategies

Keep supports using multiple vaults simultaneously, allowing you to optimize for different environments:

### Hybrid Development/Production
```bash
# Local vault for development
keep vault:add local dev-vault

# AWS SSM for staging and production  
keep vault:add ssm production-vault
```

### Cost-Optimized Strategy
```bash
# SSM for regular secrets (free tier)
keep vault:add ssm app-config

# Secrets Manager only for database credentials (rotation)
keep vault:add secretsmanager db-credentials  
```

### Multi-Region Strategy
```bash
# Primary region
keep vault:add secretsmanager primary --region=us-east-1

# DR region with replication
keep vault:add secretsmanager dr --region=us-west-2
```

## Vault Architecture

### Namespace Isolation
All vaults respect Keep's namespace system:
- **Local**: Separate directories per namespace
- **SSM**: Path-based organization (`/myapp/stage/key`)
- **Secrets Manager**: Tag-based organization with path naming

### Stage Management
Secrets are organized by stage within each vault:
- `development` - Development environment
- `staging` - Pre-production testing
- `production` - Live production environment

### Security Model
- **Local**: File-system permissions + encryption
- **AWS Vaults**: IAM policies + KMS encryption
- **Cross-Vault**: No automatic secret sharing between vaults

## Getting Started

### 1. Add Your First Vault
```bash
# For development
keep vault:add

# Follow prompts to choose vault type and configuration
```

### 2. Configure Stages
```bash
# Set secrets in different stages
keep set DB_PASSWORD "dev-secret" --stage=development --vault=local
keep set DB_PASSWORD "prod-secret" --stage=production --vault=aws-ssm
```

### 3. Verify Connectivity
```bash
# Test all vault connections
keep verify

# List configured vaults
keep vault:list
```

## Best Practices

### Development Workflow
- **Use local vaults** for development
- **Mock production vault structure** in local development
- **Test vault connectivity** in CI/CD

### Production Security
- **Use AWS vaults** for production workloads
- **Implement least-privilege IAM** policies
- **Enable CloudTrail logging** for audit trails
- **Rotate secrets regularly** (especially with Secrets Manager)

### Cost Optimization
- **Start with SSM Parameter Store** (free tier)
- **Use Secrets Manager selectively** for high-value secrets needing rotation
- **Monitor API usage** to stay within free tiers

### Team Collaboration
- **Share vault configurations** via Keep configuration files
- **Use consistent naming** across team members
- **Document vault purposes** and access patterns

## Next Steps

- **[Local Vault](./vaults/local)** - Set up file-based development storage
- **[AWS SSM Parameter Store](./vaults/aws-ssm)** - Configure cost-effective production storage  
- **[AWS Secrets Manager](./vaults/aws-secrets-manager)** - Set up premium secret management
- **[Configuration Guide](./configuration)** - Learn about Keep project setup
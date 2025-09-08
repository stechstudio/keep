# AWS Secrets Manager

AWS Secrets Manager is a premium secrets management service designed specifically for storing, retrieving, and rotating application secrets. It provides enterprise-grade security features and automatic rotation capabilities for database credentials, API keys, and other sensitive information.

## Why Choose AWS Secrets Manager?

**Built for Secrets**: Purpose-built service specifically for managing application secrets, not general configuration.

**Automatic Rotation**: Native integration with RDS, Redshift, and DocumentDB for automatic credential rotation without application downtime.

**Cross-Region Replication**: Built-in cross-region secret replication for disaster recovery and multi-region applications.

**Advanced Security**: Automatic encryption with AWS KMS, fine-grained access policies, and comprehensive audit logging.

**JSON Support**: Native support for structured secrets (JSON) allowing multiple key-value pairs in a single secret.

**Enterprise Features**: Resource policies, cross-account access, and integration with AWS CloudFormation.

## Adding a Secrets Manager Vault

Use the `vault:add` command to configure a new Secrets Manager vault:

```bash
keep vault:add
```

You'll be prompted for:

**Driver**: Select "AWS Secrets Manager" from the available vaults

**Slug**: A friendly slug for this vault (e.g., `secretsmanager`) that will be used in template placeholders

**Friendly Name**: A reference name for the vault (e.g., `MyApp Secrets Manager Vault`)

**AWS Region**: The AWS region where your secrets will be stored (e.g., `us-east-1`)

**KMS Key ID**: Optional. Leave empty to use AWS managed key (`alias/aws/secretsmanager`), or specify a custom KMS key for additional security

## IAM Permission Scenarios

Keep uses **tag-based permissions** for Secrets Manager to provide fine-grained access control. All secrets are tagged with `ManagedBy=Keep`, `Namespace={namespace}`, `Stage={stage}`, and `VaultSlug={vault}` for precise permission boundaries.

### Full Developer Access

For developers who need complete access to manage secrets across all environments in `myapp` namespace:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "ReadWriteMyAppSecrets",
      "Effect": "Allow",
      "Action": [
        "secretsmanager:GetSecretValue",
        "secretsmanager:DescribeSecret",
        "secretsmanager:ListSecretVersionIds",
        "secretsmanager:PutSecretValue",
        "secretsmanager:UpdateSecret*",
        "secretsmanager:DeleteSecret",
        "secretsmanager:RestoreSecret",
        "secretsmanager:TagResource",
        "secretsmanager:UntagResource"
      ],
      "Resource": "*",
      "Condition": {
        "StringEquals": {
          "secretsmanager:ResourceTag/Namespace": "myapp"
        }
      }
    },
    {
      "Sid": "ListSecretsAccountWide",
      "Effect": "Allow",
      "Action": [
        "secretsmanager:ListSecrets",
        "secretsmanager:BatchGetSecretValue"
      ],
      "Resource": "*"
    },
    {
      "Sid": "CreateSecretsWithNamespaceTag",
      "Effect": "Allow",
      "Action": "secretsmanager:CreateSecret",
      "Resource": "*",
      "Condition": {
        "StringEquals": {
          "aws:RequestTag/Namespace": "myapp"
        },
        "ForAllValues:StringEquals": {
          "aws:TagKeys": [
            "ManagedBy",
            "Namespace",
            "Stage",
            "VaultSlug"
          ]
        }
      }
    },
    {
      "Sid": "KmsForSecretsManagerDefaultKey",
      "Effect": "Allow",
      "Action": [
        "kms:Decrypt",
        "kms:Encrypt",
        "kms:GenerateDataKey"
      ],
      "Resource": "arn:aws:kms:*:*:alias/aws/secretsmanager"
    }
  ]
}
```

### Environment-Specific Developer Access

For developers who should only access development and staging environments:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "ReadWriteMyAppStagingAndProd",
      "Effect": "Allow",
      "Action": [
        "secretsmanager:GetSecretValue",
        "secretsmanager:DescribeSecret",
        "secretsmanager:ListSecretVersionIds",
        "secretsmanager:PutSecretValue",
        "secretsmanager:UpdateSecret",
        "secretsmanager:UpdateSecretVersionStage",
        "secretsmanager:DeleteSecret",
        "secretsmanager:RestoreSecret",
        "secretsmanager:TagResource",
        "secretsmanager:UntagResource"
      ],
      "Resource": "*",
      "Condition": {
        "StringEquals": {
          "secretsmanager:ResourceTag/ManagedBy": "Keep",
          "secretsmanager:ResourceTag/Namespace": "myapp"
        },
        "ForAnyValue:StringEquals": {
          "secretsmanager:ResourceTag/Stage": [
            "staging",
            "production"
          ]
        }
      }
    },
    {
      "Sid": "ListSecretsAccountWide",
      "Effect": "Allow",
      "Action": [
        "secretsmanager:ListSecrets",
        "secretsmanager:BatchGetSecretValue"
      ],
      "Resource": "*"
    },
    {
      "Sid": "CreateSecretsInStagingAndProd",
      "Effect": "Allow",
      "Action": "secretsmanager:CreateSecret",
      "Resource": "*",
      "Condition": {
        "StringEquals": {
          "aws:RequestTag/ManagedBy": "Keep",
          "aws:RequestTag/Namespace": "myapp"
        },
        "ForAnyValue:StringEquals": {
          "aws:RequestTag/Stage": [
            "staging",
            "production"
          ]
        },
        "ForAllValues:StringEquals": {
          "aws:TagKeys": [
            "ManagedBy",
            "Namespace",
            "Stage",
            "VaultSlug"
          ]
        }
      }
    },
    {
      "Sid": "KmsForSecretsManagerDefaultKey",
      "Effect": "Allow",
      "Action": [
        "kms:Decrypt",
        "kms:Encrypt",
        "kms:GenerateDataKey"
      ],
      "Resource": "arn:aws:kms:*:*:alias/aws/secretsmanager"
    }
  ]
}
```

### Production Deployment (Read-Only)

For production deployment processes that only need to read production secrets:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "ReadOnlyMyAppProduction",
      "Effect": "Allow",
      "Action": [
        "secretsmanager:GetSecretValue",
        "secretsmanager:DescribeSecret"
      ],
      "Resource": "*",
      "Condition": {
        "StringEquals": {
          "secretsmanager:ResourceTag/Namespace": "myapp",
          "secretsmanager:ResourceTag/Stage": "production"
        }
      }
    },
    {
      "Sid": "ListSecretsAccountWide",
      "Effect": "Allow",
      "Action": [
        "secretsmanager:ListSecrets",
        "secretsmanager:BatchGetSecretValue"
      ],
      "Resource": "*"
    },
    {
      "Sid": "KmsDecryptOnly",
      "Effect": "Allow",
      "Action": "kms:Decrypt",
      "Resource": "arn:aws:kms:*:*:alias/aws/secretsmanager"
    }
  ]
}
```

## Secret Organization

Keep organizes secrets using simple path-style naming for duplicate avoidance, with tags providing the real organizational structure:

**Secret Names:**
- `myapp/local/DB_PASSWORD`
- `myapp/staging/API_KEY` 
- `myapp/production/NIGHTWATCH_TOKEN`

**Tags for Organization:**
- `ManagedBy: Keep`
- `Namespace: myapp`
- `Stage: local|staging|production`
- `VaultSlug: secretsmanager`

## Security Best Practices

**Tag-Based Access Control**: Keep uses tags (`ManagedBy`, `Namespace`, `Stage`, `VaultSlug`) for precise IAM permissions instead of resource ARNs.

**Automatic Encryption**: All secrets are automatically encrypted at rest using AWS KMS.

**Custom KMS Keys**: Use custom KMS keys for additional control and cross-account access patterns.

**Least Privilege Access**: Use tag conditions to grant only the minimum permissions needed for each role and environment.

**Consistent Tagging**: Keep automatically applies standardized tags to all secrets for security and organization.

**Automatic Rotation**: Enable automatic rotation for database credentials and other supported secret types.

**Versioning**: Leverage automatic versioning to safely update secrets without downtime.

## Cost Considerations

**Storage**: $0.40 per secret per month

**API Requests**: $0.05 per 10,000 API calls

**Rotation**: No additional charges for rotation API calls

**Typical Usage**: More expensive than SSM Parameter Store but includes additional enterprise features and automatic rotation capabilities.

## Common Usage Patterns

### Basic Secret Management
```bash
# Set a production database password
keep set DB_PASSWORD --stage=production

# Retrieve for verification
keep get DB_PASSWORD --stage=production

# Export for deployment
keep export --stage=production --output=.env
```

### Cross-Environment Workflows
```bash
# Copy staging secrets to production
keep copy DB_PASSWORD --from=staging --to=production

# Compare environments
keep diff --stage=staging,production
```

### Template-Based Deployment
```bash
# Use secrets in templates
keep export --template=env.template --stage=production --output=.env
```

## Advanced Features

### Cross-Region Replication
Secrets Manager supports automatic cross-region replication for disaster recovery:

```bash
# Secrets automatically replicated across regions when configured
keep get DB_PASSWORD --stage=production
```

### Automatic Rotation
For supported services like RDS, enable automatic rotation:

- Database credentials rotate automatically
- Applications continue working during rotation
- Keep retrieves the current active version

## Troubleshooting

**Access Denied Errors**: Verify your IAM permissions include both Secrets Manager and KMS actions for the correct resource paths.

**Secret Not Found**: Check your secret scope configuration matches your expected naming structure.

**Encryption Issues**: Ensure your IAM role has access to the KMS key being used (either AWS managed or custom).

**Region Mismatch**: Verify you're operating in the same AWS region where your secrets are stored.

**Version Conflicts**: If using rotation, ensure you're retrieving the `AWSCURRENT` version (Keep handles this automatically).


## Next Steps

- [AWS SSM Parameter Store](./aws-ssm) - For cost-effective configuration and simple secrets
- [Deployment & Runtime](../deployment/) - Export secrets and runtime injection
- [CLI Reference](../reference/cli-reference) - Complete command documentation
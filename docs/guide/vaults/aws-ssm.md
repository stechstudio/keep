# AWS SSM Parameter Store

AWS Systems Manager Parameter Store is a powerful, cost-effective solution for storing configuration data and secrets. It provides secure, hierarchical storage for configuration data management and secrets management.

## Why Choose SSM Parameter Store?

**Cost-Effective**: Standard parameters are free (up to 10,000), with Advanced parameters costing only $0.05 per 10,000 API interactions.

**Hierarchical Organization**: Natural path-based organization (`/myapp/production/DB_PASSWORD`) that aligns perfectly with Keep's namespace system.

**Encryption Built-In**: Native integration with AWS KMS for transparent encryption of sensitive values.

**Fine-Grained Access Control**: Leverage AWS IAM for precise control over who can access which parameters in which environments.

**Version History**: Automatic versioning of parameter changes with built-in rollback capabilities.

**Cross-Service Integration**: Native integration with EC2, ECS, Lambda, and other AWS services.

## Adding an SSM Vault

Use the `vault:add` command to configure a new SSM vault:

```bash
keep vault:add
```

You'll be prompted for:

**Driver**: Select "AWS Systems Manager Parameter Store" from the available vaults

**Slug**: A friendly slug for this vault (e.g., `myapp-ssm`) that will be used in template placeholders

**Friendly Name**: A reference name for the vault (e.g., `MyApp SSM Vault`)

**AWS Region**: The AWS region where your parameters will be stored (e.g., `us-east-1`)

**Parameter Prefix**: Optional base path for all parameters. If you specify `myapp`, your parameters will be stored as `/myapp/[namespace]/[stage]/[key]`

**KMS Key ID**: Optional. Leave empty to use AWS managed key (`alias/aws/ssm`), or specify a custom KMS key for additional security

## IAM Permission Scenarios

Let's look at how to set up IAM permissions for different roles in your organization when using AWS SSM Parameter Store with Keep. These examples assume a namespace of "myapp" and use the default KMS key for SSM.

### Full Developer Access

For developers who need complete access to manage secrets across all environments in `myapp`:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "ssm:GetParameter",
                "ssm:GetParameters",
                "ssm:GetParametersByPath",
                "ssm:GetParameterHistory",
                "ssm:PutParameter",
                "ssm:DeleteParameter",
                "ssm:LabelParameterVersion",
                "ssm:UnlabelParameterVersion"
            ],
            "Resource": "arn:aws:ssm:*:*:parameter/myapp/*"
        },
        {
            "Effect": "Allow",
            "Action": [
                "kms:Decrypt",
                "kms:Encrypt",
                "kms:GenerateDataKey"
            ],
            "Resource": [
                "arn:aws:kms:*:*:alias/aws/ssm"
            ]
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
            "Effect": "Allow",
            "Action": [
                "ssm:GetParameter",
                "ssm:GetParameters",
                "ssm:GetParametersByPath",
                "ssm:PutParameter",
                "ssm:DeleteParameter",
                "ssm:GetParameterHistory"
            ],
            "Resource": [
                "arn:aws:ssm:*:*:parameter/myapp/development/*",
                "arn:aws:ssm:*:*:parameter/myapp/staging/*"
            ]
        },
        {
            "Effect": "Allow",
            "Action": [
                "kms:Decrypt",
                "kms:Encrypt",
                "kms:GenerateDataKey"
            ],
            "Resource": [
                "arn:aws:kms:*:*:alias/aws/ssm"
            ]
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
            "Effect": "Allow",
            "Action": [
                "ssm:GetParameter",
                "ssm:GetParameters",
                "ssm:GetParametersByPath"
            ],
            "Resource": "arn:aws:ssm:*:*:parameter/myapp/production/*"
        },
        {
            "Effect": "Allow",
            "Action": [
                "kms:Decrypt"
            ],
            "Resource": [
                "arn:aws:kms:*:*:alias/aws/ssm"
            ]
        }
    ]
}
```

## Parameter Organization

With the example configuration above, Keep will organize your parameters like this:

```
/myapp/
├── development/
│   ├── DB_PASSWORD
│   ├── API_KEY
│   └── NIGHTWATCH_TOKEN
├── staging/
│   ├── DB_PASSWORD
│   ├── API_KEY
│   └── NIGHTWATCH_TOKEN
└── production/
    ├── DB_PASSWORD
    ├── API_KEY
    └── NIGHTWATCH_TOKEN
```

## Security Best Practices

**Use SecureString Type**: Keep automatically creates parameters as `SecureString` when you mark secrets as secure, ensuring they're encrypted at rest.

**Custom KMS Keys**: For highly sensitive applications, use a custom KMS key instead of the AWS managed key for additional control.

**Least Privilege Access**: Grant only the minimum IAM permissions needed for each role.

**Parameter Naming**: Use consistent, descriptive parameter names that align with your application's configuration.

**Regular Rotation**: Leverage Keep's versioning support to regularly rotate sensitive credentials.

## Cost Considerations

**Standard Parameters**: Free for up to 10,000 parameters, then $0.05 per 10,000 API interactions

**Advanced Parameters**: $0.05 per 10,000 API interactions (allows larger values and parameter policies)

**Storage**: No additional storage costs

**Typical Usage**: Most applications will stay within the free tier for parameter storage, with minimal API interaction costs.

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
keep merge env.template --stage=production --vault=ssm --output=.env
```

## Troubleshooting

**Access Denied Errors**: Verify your IAM permissions include both SSM and KMS actions for the correct resource paths.

**Parameter Not Found**: Check your parameter prefix and namespace configuration match your expected path structure.

**Encryption Issues**: Ensure your IAM role has access to the KMS key being used (either AWS managed or custom).

**Region Mismatch**: Verify you're operating in the same AWS region where your parameters are stored.


## Next Steps

- [AWS Secrets Manager](./aws-secrets-manager) - For more advanced secret rotation features
- [Template System](../templates) - Learn how to use SSM parameters in templates
- [Multi-Environment Setup](../../examples/multi-environment) - Best practices for organizing environments
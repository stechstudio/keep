# Introduction

Keep is a toolkit for collaborative, secure management of secrets across applications, environments, and teams. It provides a consistent interface for managing secrets whether they're stored locally during development or in cloud services like AWS SSM Parameter Store or AWS Secrets Manager in production.

## What is Keep?

Keep addresses the common challenge of managing environment variables and secrets across different stages of development and deployment. Instead of passing around `.env` files through insecure channels, Keep provides:

- **Centralized Secret Storage**: Store secrets in various backends (local, AWS SSM, AWS Secrets Manager)
- **Environment Staging**: Organize secrets by stages (development, staging, production)
- **Template-Based Generation**: Generate configuration files from templates with proper secret injection
- **Team Collaboration**: Share access to secrets without exposing their values
- **Laravel Integration**: Seamless integration with Laravel applications

## Key Concepts

### Vaults
Vaults are storage backends for your secrets. Keep supports:
- **Local Vault**: File-based storage for development
- **AWS SSM**: AWS Systems Manager Parameter Store
- **AWS Secrets Manager**: AWS managed secrets service

### Stages
Stages represent different environments like `development`, `staging`, and `production`. Each vault can store secrets for multiple stages, allowing you to promote secrets through your deployment pipeline.

### Contexts
A context combines a vault and stage, written as `vault:stage` (e.g., `myapp:production`). This tells Keep exactly where to find or store a secret.

### Templates
Templates are configuration files with placeholders that get replaced with actual secret values. This allows you to generate `.env` files, configuration files, or any text-based configuration.

## Getting Started

Ready to start using Keep? Head over to the [Installation Guide](./installation) to set up Keep in your project.
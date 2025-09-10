# Introduction

Keep is a PHP toolkit for managing secrets across applications, environments, and teams. It provides a consistent CLI interface for AWS SSM Parameter Store and AWS Secrets Manager.

## Why Keep?

Managing secrets across local, staging, and production environments is challenging. Keep solves this by providing:

- **Unified Interface**: One CLI for all your secret vaults
- **Environment Organization**: Separate secrets by environment (local, staging, production)
- **Template Generation**: Build configuration files from templates with automatic secret replacement
- **Team Collaboration**: Share vault access without exposing secret values
- **Security First**: Encrypted storage, masked output, and secure AWS integration
- **Deployment Ready**: Designed for CI/CD pipelines and automated workflows, supporting .env file generation or direct environment variable injection

## Getting Started

Head to the [Installation Guide](./installation) to set up Keep in your project.
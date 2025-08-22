# Introduction

Keep is a PHP toolkit for collaborative, secure management of secrets across applications, environments, and teams. It provides a consistent interface for managing secrets whether they're stored locally during development or in cloud services like AWS SSM Parameter Store or AWS Secrets Manager in production.

## What is Keep?

Keep addresses the common challenge of managing environment variables and secrets across different stages of development and deployment, between team members and in deployment pipelines. 

- **Centralized Secret Storage**: Store secrets in various backends (local, AWS SSM, AWS Secrets Manager)
- **Environment Staging**: Organize secrets by stages (development, staging, production)
- **Template-Based Generation**: Generate configuration files from templates with proper secret injection
- **Team Collaboration**: Share access to secrets without exposing their values
- **Laravel Integration**: Seamless integration with Laravel applications

## Vaults Supported

Keep uses a driver-based architecture to support multiple vaults for storing secrets. Currently, it supports:

- **AWS SSM**: AWS Systems Manager Parameter Store
- **AWS Secrets Manager**: AWS managed secrets service

## Getting Started

Ready to start using Keep? Head over to the [Installation Guide](./installation) to set up Keep in your project.
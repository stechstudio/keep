<?php

namespace STS\Keep\Data;

use InvalidArgumentException;
use STS\Keep\Data\Concerns\InteractsWithJsonFiles;

class Settings
{
    use InteractsWithJsonFiles;

    public function __construct(
        protected string $appName,
        protected string $namespace,
        protected array $envs,
        protected ?string $defaultVault = null,
        protected ?string $templatePath = null,
        protected ?string $createdAt = null,
        protected ?string $updatedAt = null,
        protected string $version = '1.0'
    ) {
        $this->validate();
        $this->createdAt ??= date('c');
        $this->updatedAt = date('c');
        $this->templatePath ??= 'env';
    }

    public static function load(): ?static
    {
        $settingsPath = getcwd().'/.keep/settings.json';

        return file_exists($settingsPath)
            ? static::fromFile($settingsPath)
            : null;
    }

    public static function fromArray(array $data): static
    {
        // Validate required fields exist (check app_name and namespace first)
        $required = ['app_name', 'namespace'];
        foreach ($required as $field) {
            if (! isset($data[$field])) {
                throw new InvalidArgumentException("Missing required setting: {$field}");
            }
        }
        
        // Get the envs array
        $envs = $data['envs'] ?? null;
        
        if (!$envs) {
            throw new InvalidArgumentException("Missing required setting: envs");
        }

        return new static(
            appName: $data['app_name'],
            namespace: $data['namespace'],
            envs: $envs,
            defaultVault: $data['default_vault'] ?? null,
            templatePath: $data['template_path'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            version: $data['version'] ?? '1.0'
        );
    }

    public function toArray(): array
    {
        return [
            'app_name' => $this->appName,
            'namespace' => $this->namespace,
            'envs' => $this->envs,
            'default_vault' => $this->defaultVault,
            'template_path' => $this->templatePath(), // Use getter for default
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'version' => $this->version,
        ];
    }

    protected function validate(): void
    {
        // Validate app name
        if (empty(trim($this->appName))) {
            throw new InvalidArgumentException('App name cannot be empty');
        }

        if (strlen($this->appName) > 100) {
            throw new InvalidArgumentException('App name cannot exceed 100 characters');
        }

        // Validate namespace (similar to secret key validation)
        $trimmedNamespace = trim($this->namespace);
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $trimmedNamespace)) {
            throw new InvalidArgumentException(
                'Namespace must contain only letters, numbers, underscores, and hyphens'
            );
        }

        if (strlen($trimmedNamespace) > 100) {
            throw new InvalidArgumentException('Namespace cannot exceed 100 characters');
        }

        // Validate envs
        if (empty($this->envs)) {
            throw new InvalidArgumentException('At least one environment must be defined');
        }

        foreach ($this->envs as $env) {
            if (! is_string($env) || empty(trim($env))) {
                throw new InvalidArgumentException('All environments must be non-empty strings');
            }

            if (! preg_match('/^[a-z0-9_-]+$/', $env)) {
                throw new InvalidArgumentException(
                    "Environment '{$env}' must contain only lowercase letters, numbers, underscores, and hyphens"
                );
            }

            if (strlen($env) > 50) {
                throw new InvalidArgumentException("Environment '{$env}' cannot exceed 50 characters");
            }
        }

        // Check for duplicate envs
        if (count($this->envs) !== count(array_unique($this->envs))) {
            throw new InvalidArgumentException('Duplicate environments are not allowed');
        }

        // Validate default vault if provided
        if ($this->defaultVault !== null) {
            if (! preg_match('/^[A-Za-z0-9_-]+$/', $this->defaultVault)) {
                throw new InvalidArgumentException(
                    'Default vault name must contain only letters, numbers, underscores, and hyphens'
                );
            }
        }

        // Validate version
        if (! preg_match('/^\d+\.\d+$/', $this->version)) {
            throw new InvalidArgumentException('Version must be in format "major.minor" (e.g., "1.0")');
        }
    }

    // Getters
    public function appName(): string
    {
        return $this->appName;
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function envs(): array
    {
        return $this->envs;
    }
    

    public function defaultVault(): ?string
    {
        return $this->defaultVault;
    }

    public function templatePath(): string
    {
        return $this->templatePath ?? 'env';
    }

    public function version(): string
    {
        return $this->version;
    }

    public function createdAt(): ?string
    {
        return $this->createdAt;
    }

    public function updatedAt(): ?string
    {
        return $this->updatedAt;
    }

    // Save method - Settings knows how to save itself
    public function save(): void
    {
        $this->saveToFile(getcwd().'/.keep/settings.json');
    }

    // Mutation methods (return new instance for immutability)
    public function withDefaultVault(?string $defaultVault): static
    {
        return new static(
            appName: $this->appName,
            namespace: $this->namespace,
            envs: $this->envs,
            defaultVault: $defaultVault,
            templatePath: $this->templatePath,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            version: $this->version
        );
    }

    public function withEnvs(array $envs): static
    {
        return new static(
            appName: $this->appName,
            namespace: $this->namespace,
            envs: $envs,
            defaultVault: $this->defaultVault,
            templatePath: $this->templatePath,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            version: $this->version
        );
    }
        
    public function withTemplatePath(?string $templatePath): static
    {
        return new static(
            appName: $this->appName,
            namespace: $this->namespace,
            envs: $this->envs,
            defaultVault: $this->defaultVault,
            templatePath: $templatePath,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            version: $this->version
        );
    }

    public function hasEnv(string $env): bool
    {
        return in_array($env, $this->envs, true);
    }
    
    public function get(string $key, mixed $default = null): mixed
    {
        return match ($key) {
            'app_name' => $this->appName,
            'namespace' => $this->namespace,
            'envs' => $this->envs,
            'default_vault' => $this->defaultVault,
            'template_path' => $this->templatePath(), // Use getter for default
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'version' => $this->version,
            default => $default,
        };
    }
}
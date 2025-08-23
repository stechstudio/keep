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
        protected array $stages,
        protected ?string $defaultVault = null,
        protected ?string $createdAt = null,
        protected ?string $updatedAt = null,
        protected string $version = '1.0'
    ) {
        $this->validate();
        $this->createdAt ??= date('c');
        $this->updatedAt = date('c');
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
        // Validate required fields exist
        $required = ['app_name', 'namespace', 'stages'];
        foreach ($required as $field) {
            if (! isset($data[$field])) {
                throw new InvalidArgumentException("Missing required setting: {$field}");
            }
        }

        return new static(
            appName: $data['app_name'],
            namespace: $data['namespace'],
            stages: $data['stages'],
            defaultVault: $data['default_vault'] ?? null,
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
            'stages' => $this->stages,
            'default_vault' => $this->defaultVault,
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

        // Validate stages
        if (empty($this->stages)) {
            throw new InvalidArgumentException('At least one stage must be defined');
        }

        foreach ($this->stages as $stage) {
            if (! is_string($stage) || empty(trim($stage))) {
                throw new InvalidArgumentException('All stages must be non-empty strings');
            }

            if (! preg_match('/^[a-z0-9_-]+$/', $stage)) {
                throw new InvalidArgumentException(
                    "Stage '{$stage}' must contain only lowercase letters, numbers, underscores, and hyphens"
                );
            }

            if (strlen($stage) > 50) {
                throw new InvalidArgumentException("Stage '{$stage}' cannot exceed 50 characters");
            }
        }

        // Check for duplicate stages
        if (count($this->stages) !== count(array_unique($this->stages))) {
            throw new InvalidArgumentException('Duplicate stages are not allowed');
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

    public function stages(): array
    {
        return $this->stages;
    }

    public function defaultVault(): ?string
    {
        return $this->defaultVault;
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
            stages: $this->stages,
            defaultVault: $defaultVault,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            version: $this->version
        );
    }

    public function withStages(array $stages): static
    {
        return new static(
            appName: $this->appName,
            namespace: $this->namespace,
            stages: $stages,
            defaultVault: $this->defaultVault,
            createdAt: $this->createdAt,
            updatedAt: $this->updatedAt,
            version: $this->version
        );
    }

    public function hasStage(string $stage): bool
    {
        return in_array($stage, $this->stages, true);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return match ($key) {
            'app_name' => $this->appName,
            'namespace' => $this->namespace,
            'stages' => $this->stages,
            'default_vault' => $this->defaultVault,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'version' => $this->version,
            default => $default,
        };
    }
}

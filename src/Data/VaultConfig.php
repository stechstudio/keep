<?php

namespace STS\Keep\Data;

use InvalidArgumentException;
use STS\Keep\Data\Concerns\InteractsWithJsonFiles;

class VaultConfig
{
    use InteractsWithJsonFiles;
    public function __construct(
        protected string $slug,
        protected string $driver,
        protected string $name,
        protected array $config = []
    ) {
        $this->validate();
    }

    public static function fromArray(array $data): static
    {
        // Validate required fields exist
        $required = ['slug', 'driver', 'name'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }

        // Extract slug, driver and name, rest goes into config
        $slug = $data['slug'];
        $driver = $data['driver'];
        $name = $data['name'];
        $config = array_diff_key($data, ['slug' => null, 'driver' => null, 'name' => null]);

        return new static(
            slug: $slug,
            driver: $driver,
            name: $name,
            config: $config
        );
    }

    public function toArray(): array
    {
        return array_merge([
            'slug' => $this->slug,
            'driver' => $this->driver,
            'name' => $this->name,
        ], $this->config);
    }


    protected function validate(): void
    {
        // Validate slug
        if (empty(trim($this->slug))) {
            throw new InvalidArgumentException('Slug cannot be empty');
        }

        if (!preg_match('/^[a-z0-9_-]+$/', $this->slug)) {
            throw new InvalidArgumentException(
                'Slug must contain only lowercase letters, numbers, underscores, and hyphens'
            );
        }

        if (strlen($this->slug) > 50) {
            throw new InvalidArgumentException('Slug cannot exceed 50 characters');
        }

        // Validate driver name
        if (empty(trim($this->driver))) {
            throw new InvalidArgumentException('Driver cannot be empty');
        }

        if (!preg_match('/^[a-z0-9_-]+$/', $this->driver)) {
            throw new InvalidArgumentException(
                'Driver must contain only lowercase letters, numbers, underscores, and hyphens'
            );
        }

        if (strlen($this->driver) > 50) {
            throw new InvalidArgumentException('Driver name cannot exceed 50 characters');
        }

        // Validate vault name
        if (empty(trim($this->name))) {
            throw new InvalidArgumentException('Vault name cannot be empty');
        }

        if (strlen($this->name) > 100) {
            throw new InvalidArgumentException('Vault name cannot exceed 100 characters');
        }

        // Validate config array contains only scalar values or arrays
        $this->validateConfigStructure($this->config);
    }

    protected function validateConfigStructure(array $config, string $path = ''): void
    {
        foreach ($config as $key => $value) {
            $currentPath = $path ? "{$path}.{$key}" : $key;

            // Validate key names
            if (!is_string($key) || empty(trim($key))) {
                throw new InvalidArgumentException("Configuration key '{$currentPath}' must be a non-empty string");
            }

            // Validate value types
            if (is_array($value)) {
                $this->validateConfigStructure($value, $currentPath);
            } elseif (!is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException(
                    "Configuration value at '{$currentPath}' must be scalar, array, or null. " .
                    "Got: " . gettype($value)
                );
            }

            // Validate string values aren't too long
            if (is_string($value) && strlen($value) > 1000) {
                throw new InvalidArgumentException(
                    "Configuration value at '{$currentPath}' cannot exceed 1000 characters"
                );
            }
        }
    }

    // Getters
    public function slug(): string
    {
        return $this->slug;
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function config(): array
    {
        return $this->config;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->config);
    }

    // Save method - VaultConfiguration knows how to save itself
    public function save(): void
    {
        $vaultDir = getcwd() . '/.keep/vaults';
        
        // Ensure directory exists
        if (!is_dir($vaultDir)) {
            mkdir($vaultDir, 0755, true);
        }
        
        $filePath = $vaultDir . '/' . $this->slug . '.json';
        $this->saveToFile($filePath);
    }

    // Mutation methods (return new instance for immutability)
    public function withConfig(array $config): static
    {
        return new static(
            slug: $this->slug,
            driver: $this->driver,
            name: $this->name,
            config: $config
        );
    }

    public function withConfigValue(string $key, mixed $value): static
    {
        $newConfig = $this->config;
        $newConfig[$key] = $value;

        return new static(
            slug: $this->slug,
            driver: $this->driver,
            name: $this->name,
            config: $newConfig
        );
    }

    public function withoutConfigValue(string $key): static
    {
        $newConfig = $this->config;
        unset($newConfig[$key]);

        return new static(
            slug: $this->slug,
            driver: $this->driver,
            name: $this->name,
            config: $newConfig
        );
    }
}
<?php

namespace STS\Keep;

use Illuminate\Support\Arr;
use STS\Keep\Vaults\AbstractVault;
use STS\Keep\Vaults\AwsSecretsManagerVault;
use STS\Keep\Vaults\AwsSsmVault;

class KeepManager
{
    protected array $availableVaults = [
        AwsSsmVault::class,
        AwsSecretsManagerVault::class,
    ];

    protected array $loadedVaults = [];

    public function __construct(protected array $settings, protected array $configuredVaults)
    {
    }
    
    public function isInitialized(): bool
    {
        return !empty($this->settings) && 
               isset($this->settings['app_name']);
    }

    public function getNamespace(): string
    {
        return $this->settings['namespace'] ?? 'default';
    }
    
    public function getSettings(): array
    {
        return $this->settings;
    }
    
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function setSetting(string $key, mixed $value): static
    {
        $this->settings[$key] = $value;
        return $this;
    }

    public function getAvailableVaults(): array
    {
        return $this->availableVaults;
    }
    
    public function getConfiguredVaults(): array
    {
        return $this->configuredVaults;
    }

    public function getDefaultVault(): ?string
    {
        return $this->settings['default_vault'] ?? null;
    }

    public function getStages(): array
    {
        return $this->settings['stages'] ?? [];
    }

    public function vault(string $name, string $stage): AbstractVault
    {
        $cacheKey = "{$name}:{$stage}";

        if (isset($this->loadedVaults[$cacheKey])) {
            return $this->loadedVaults[$cacheKey];
        }

        if (!isset($this->configuredVaults[$name])) {
            throw new \InvalidArgumentException("Vault '{$name}' is not configured.");
        }

        $config = $this->configuredVaults[$name];
        $driver = $config['driver'] ?? null;

        if (!$driver) {
            throw new \InvalidArgumentException("Vault '{$name}' does not have a driver configured.");
        }

        if(!$driverClass = $this->driverClass($driver)) {
            throw new \InvalidArgumentException("Vault driver '{$driver}' for '{$name}' is not available.");
        }

        $vault = new $driverClass($name, $config, $stage);
        $this->loadedVaults[$cacheKey] = $vault;

        return $vault;
    }

    protected function driverClass($name): ?string
    {
        return Arr::first($this->availableVaults, fn($class) => $class::DRIVER === $name);
    }
    
    /**
     * Add a vault driver class to available vaults (useful for testing)
     */
    public function addVaultDriver(string $driverClass): static
    {
        $this->availableVaults[] = $driverClass;
        return $this;
    }

    /**
     * Clear the vault cache - useful for testing
     */
    public function clearVaultCache(): static
    {
        $this->loadedVaults = [];
        return $this;
    }
}

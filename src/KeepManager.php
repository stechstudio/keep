<?php

namespace STS\Keep;

use Illuminate\Support\Str;
use InvalidArgumentException;
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
    
    public function getSettings(): array
    {
        return $this->settings;
    }
    
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
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

    /**
     * Clear the vault cache - useful for testing
     */
    public function clearVaultCache(): static
    {
        $this->loadedVaults = [];
        return $this;
    }
}

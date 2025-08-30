<?php

namespace STS\Keep;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use STS\Keep\Data\Collections\VaultConfigCollection;
use STS\Keep\Data\Settings;
use STS\Keep\Tests\Support\TestVault;
use STS\Keep\Vaults\AbstractVault;
use STS\Keep\Vaults\AwsSecretsManagerVault;
use STS\Keep\Vaults\AwsSsmVault;

class KeepManager
{
    protected array $availableVaults = [
        AwsSsmVault::class,
        AwsSecretsManagerVault::class,
        TestVault::class,
    ];

    protected array $loadedVaults = [];

    public function __construct(protected ?Settings $settings, protected VaultConfigCollection $configuredVaults) {}

    public function isInitialized(): bool
    {
        return $this->settings !== null;
    }

    public function getNamespace(): string
    {
        return $this->settings?->namespace() ?? 'default';
    }

    public function getSettings(): array
    {
        return $this->settings?->toArray() ?? [];
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings?->get($key, $default) ?? $default;
    }

    public function getAvailableVaults(): array
    {
        return $this->availableVaults;
    }

    public function getConfiguredVaults(): Collection
    {
        return $this->configuredVaults;
    }

    public function getDefaultVault(): ?string
    {
        return $this->settings?->defaultVault();
    }

    public function getStages(): array
    {
        return $this->settings?->stages() ?? [];
    }

    public function vault(string $name, string $stage): AbstractVault
    {
        $cacheKey = "{$name}:{$stage}";

        if (isset($this->loadedVaults[$cacheKey])) {
            return $this->loadedVaults[$cacheKey];
        }

        if (! $this->configuredVaults->has($name)) {
            throw new \InvalidArgumentException("Vault '{$name}' is not configured.");
        }

        $config = $this->configuredVaults->get($name);
        $driver = $config->driver();

        if (! $driver) {
            throw new \InvalidArgumentException("Vault '{$name}' does not have a driver configured.");
        }

        if (! $driverClass = $this->driverClass($driver)) {
            throw new \InvalidArgumentException("Vault driver '{$driver}' for '{$name}' is not available.");
        }

        $vault = new $driverClass($name, $config->toArray(), $stage);
        $this->loadedVaults[$cacheKey] = $vault;

        return $vault;
    }

    protected function driverClass($name): ?string
    {
        return Arr::first($this->availableVaults, fn ($class) => $class::DRIVER === $name);
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

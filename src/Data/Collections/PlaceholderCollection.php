<?php

namespace STS\Keep\Data\Collections;

use Illuminate\Support\Collection;
use STS\Keep\Data\Placeholder;
use STS\Keep\Data\PlaceholderValidationResult;

class PlaceholderCollection extends Collection
{
    /**
     * Validate all placeholders against the specified vault and stage
     *
     * @return Collection<PlaceholderValidationResult>
     */
    public function validate(string $defaultVault, string $stage): Collection
    {
        return $this->map(fn (Placeholder $placeholder) => $placeholder->validate($defaultVault, $stage));
    }

    /**
     * Get all placeholders that reference a specific vault
     */
    public function forVault(string $vaultName, ?string $defaultVault = null): static
    {
        return $this->filter(function (Placeholder $placeholder) use ($vaultName, $defaultVault) {
            $effectiveVault = $placeholder->vault ?? $defaultVault;

            return $effectiveVault === $vaultName;
        });
    }

    /**
     * Get all unique vault names referenced by these placeholders
     */
    public function getReferencedVaults(string $defaultVault): array
    {
        return $this->map(fn (Placeholder $placeholder) => $placeholder->getEffectiveVault($defaultVault))
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get all unique keys referenced by these placeholders
     */
    public function getReferencedKeys(): array
    {
        return $this->map(fn (Placeholder $placeholder) => $placeholder->key)
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get keys referenced in a specific vault
     */
    public function getReferencedKeysForVault(string $vaultName, string $defaultVault): array
    {
        return $this->filter(fn (Placeholder $placeholder) => $placeholder->getEffectiveVault($defaultVault) === $vaultName)
            ->map(fn (Placeholder $placeholder) => $placeholder->key)
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Convert to legacy array format for backward compatibility
     */
    public function toLegacyArray(): array
    {
        return $this->map(fn (Placeholder $placeholder) => $placeholder->toArray())->all();
    }
}

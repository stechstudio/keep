<?php

namespace STS\Keep\Services;

use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Facades\Keep;

class SecretLoader
{
    /**
     * Load secrets from multiple vaults and merge them.
     * Later vaults override earlier ones for duplicate keys.
     */
    public function loadFromVaults(array $vaultNames, string $stage): SecretCollection
    {
        $allSecrets = new SecretCollection;

        foreach ($vaultNames as $vaultName) {
            $vault = Keep::vault($vaultName, $stage);
            $vaultSecrets = $vault->list();

            // Merge secrets, later vaults override earlier ones for duplicate keys
            $allSecrets = $allSecrets->merge($vaultSecrets);
        }

        return $allSecrets;
    }

    /**
     * Get all configured vault names.
     */
    public function getAllVaultNames(): array
    {
        return Keep::getConfiguredVaults()->keys()->toArray();
    }
}

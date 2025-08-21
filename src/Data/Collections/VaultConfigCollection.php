<?php

namespace STS\Keep\Data\Collections;

use Illuminate\Support\Collection;
use STS\Keep\Data\VaultConfig;

class VaultConfigCollection extends Collection
{
    public static function load(): static
    {
        $vaultsDir = getcwd().'/.keep/vaults';

        if (! is_dir($vaultsDir)) {
            return new static;
        }

        $vaultFiles = glob($vaultsDir.'/*.json');
        if ($vaultFiles === false) {
            throw new \RuntimeException("Cannot read vault directory: {$vaultsDir}");
        }

        return (new static($vaultFiles))
            ->mapWithKeys(function ($file) {
                $vaultName = basename($file, '.json');

                // Load raw data and ensure slug is set
                $rawData = json_decode(file_get_contents($file), true);
                if (! isset($rawData['slug'])) {
                    $rawData['slug'] = $vaultName;
                }

                return [$rawData['slug'] => VaultConfig::fromArray($rawData)];
            });
    }
}

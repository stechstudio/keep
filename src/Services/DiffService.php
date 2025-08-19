<?php

namespace STS\Keep\Services;

use Illuminate\Support\Collection;
use STS\Keep\Data\SecretDiff;
use STS\Keep\Data\SecretsCollection;
use STS\Keep\Facades\Keep;

class DiffService
{
    public function compare(array $vaults, array $environments): Collection
    {
        $allSecrets = $this->gatherSecrets($vaults, $environments);
        $allKeys = $this->extractAllKeys($allSecrets);

        return $allKeys->map(function (string $key) use ($allSecrets) {
            $diff = new SecretDiff($key);

            foreach ($allSecrets as $vaultEnv => $secrets) {
                $secret = $secrets->getByKey($key);
                $diff->setValue($vaultEnv, $secret);
            }

            return $diff;
        })->sortBy('key')->values();
    }

    public function generateSummary(Collection $diffs, array $vaults, array $environments): array
    {
        $totalSecrets = $diffs->count();
        $identical = $diffs->filter(fn (SecretDiff $diff) => $diff->status() === SecretDiff::STATUS_IDENTICAL)->count();
        $different = $diffs->filter(fn (SecretDiff $diff) => $diff->status() === SecretDiff::STATUS_DIFFERENT)->count();
        $incomplete = $diffs->filter(fn (SecretDiff $diff) => $diff->status() === SecretDiff::STATUS_INCOMPLETE)->count();

        return [
            'total_secrets' => $totalSecrets,
            'identical' => $identical,
            'different' => $different,
            'incomplete' => $incomplete,
            'identical_percentage' => $totalSecrets > 0 ? round(($identical / $totalSecrets) * 100) : 0,
            'different_percentage' => $totalSecrets > 0 ? round(($different / $totalSecrets) * 100) : 0,
            'incomplete_percentage' => $totalSecrets > 0 ? round(($incomplete / $totalSecrets) * 100) : 0,
            'environments_compared' => implode(', ', $environments),
            'vaults_compared' => implode(', ', $vaults),
        ];
    }

    protected function gatherSecrets(array $vaults, array $environments): array
    {
        $allSecrets = [];

        foreach ($vaults as $vaultName) {
            foreach ($environments as $environment) {
                $vaultEnvKey = "{$vaultName}.{$environment}";

                try {
                    $allSecrets[$vaultEnvKey] = Keep::vault($vaultName)->forEnvironment($environment)->list();
                } catch (\Exception $e) {
                    // If we can't access a vault/environment combination, treat it as empty
                    $allSecrets[$vaultEnvKey] = new SecretsCollection([]);
                }
            }
        }

        return $allSecrets;
    }

    protected function extractAllKeys(array $allSecrets): Collection
    {
        $allKeys = collect();

        foreach ($allSecrets as $secrets) {
            $allKeys = $allKeys->merge($secrets->allKeys());
        }

        return $allKeys->unique()->sort()->values();
    }
}

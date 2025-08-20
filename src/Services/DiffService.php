<?php

namespace STS\Keep\Services;

use Illuminate\Support\Collection;
use STS\Keep\Data\SecretDiff;
use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Facades\Keep;

class DiffService
{
    public function compare(array $vaults, array $stages): Collection
    {
        $allSecrets = $this->gatherSecrets($vaults, $stages);
        $allKeys = $this->extractAllKeys($allSecrets);

        return $allKeys->map(function (string $key) use ($allSecrets) {
            $diff = new SecretDiff($key);

            foreach ($allSecrets as $vaultStage => $secrets) {
                $secret = $secrets->getByKey($key);
                $diff->setValue($vaultStage, $secret);
            }

            return $diff;
        })->sortBy('key')->values();
    }

    public function generateSummary(Collection $diffs, array $vaults, array $stages): array
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
            'stages_compared' => implode(', ', $stages),
            'vaults_compared' => implode(', ', $vaults),
        ];
    }

    protected function gatherSecrets(array $vaults, array $stages): array
    {
        $allSecrets = [];

        foreach ($vaults as $vaultName) {
            foreach ($stages as $stage) {
                $vaultStageKey = "{$vaultName}.{$stage}";

                try {
                    $allSecrets[$vaultStageKey] = Keep::vault($vaultName, $stage)->list();
                } catch (\Exception $e) {
                    // If we can't access a vault/stage combination, treat it as empty
                    $allSecrets[$vaultStageKey] = new SecretCollection([]);
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

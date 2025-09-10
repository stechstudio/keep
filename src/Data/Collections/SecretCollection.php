<?php

namespace STS\Keep\Data\Collections;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use STS\Keep\Data\Concerns\FormatsEnvValues;
use STS\Keep\Data\Secret;
use STS\Keep\Data\SecretDiff;
use STS\Keep\Data\Template;
use STS\Keep\Facades\Keep;

class SecretCollection extends Collection
{
    use FormatsEnvValues;

    public function sorted(): static
    {
        return $this->sortBy->key()->values();
    }

    public function toKeyValuePair(): static
    {
        return $this->mapWithKeys(fn (Secret $secret) => [$secret->key() => $secret->value()]);
    }

    public function toEnvString()
    {
        return $this->map(fn (Secret $secret) => $secret->sanitizedKey().'='.$this->formatEnvValue($secret->value())
        )->implode(PHP_EOL);
    }

    public function toCsvString(): string
    {
        $csv = "Key,Value,Vault,Environment,Modified\n";
        
        $this->each(function (Secret $secret) use (&$csv) {
            $key = $this->escapeCsvField($secret->key());
            $value = $this->escapeCsvField($secret->value());
            $vault = $this->escapeCsvField($secret->vault()?->name() ?? '');
            $env = $this->escapeCsvField($secret->env() ?? '');
            $modified = $this->escapeCsvField($secret->lastModified()?->toIso8601String() ?? '');
            
            $csv .= "{$key},{$value},{$vault},{$env},{$modified}\n";
        });
        
        return $csv;
    }

    protected function escapeCsvField(string $field): string
    {
        if (preg_match('/[,"\n\r]/', $field)) {
            return '"' . str_replace('"', '""', $field) . '"';
        }
        return $field;
    }

    public function filterByPatterns(?string $only = null, ?string $except = null): static
    {
        $onlyPatterns = collect(array_filter(array_map('trim', explode(',', $only ?? ''))));
        $exceptPatterns = collect(array_filter(array_map('trim', explode(',', $except ?? ''))));

        return $this->filter(function (Secret $secret) use ($onlyPatterns, $exceptPatterns) {
            $key = $secret->key();

            $matchesOnly = $onlyPatterns->isEmpty() || $onlyPatterns->contains(fn ($pattern) => Str::is($pattern, $key));
            $matchesExcept = $exceptPatterns->isNotEmpty() && $exceptPatterns->contains(fn ($pattern) => Str::is($pattern, $key));

            return $matchesOnly && ! $matchesExcept;
        });
    }

    public function allKeys(): static
    {
        return $this->map->key()->values();
    }

    public function hasKey(string $key): bool
    {
        return $this->contains(fn (Secret $secret) => $secret->key() === $key);
    }

    public function getByKey(string $key): ?Secret
    {
        return $this->first(fn (Secret $secret) => $secret->key() === $key) ?: null;
    }

    public function getByPath(string $path): ?Secret
    {
        return $this->first(fn (Secret $secret) => $secret->path() === $path) ?: null;
    }

    public function mapToOnly($keys = [])
    {
        return $this->map->only($keys);
    }

    public function toApiArray(bool $unmask = false): array
    {
        return $this->map(fn (Secret $secret) => $secret->toApiArray($unmask))
            ->values()
            ->toArray();
    }

    /**
     * Load secrets from multiple vaults and merge them.
     * Later vaults override earlier ones for duplicate keys.
     */
    public static function loadFromVaults(array $vaultNames, string $env): static
    {
        $allSecrets = new static;

        foreach ($vaultNames as $vaultName) {
            $vault = Keep::vault($vaultName, $env);
            $vaultSecrets = $vault->list();
            $allSecrets = $allSecrets->merge($vaultSecrets);
        }

        return $allSecrets;
    }

    public function toEnvironment(bool $inheritCurrent = true): array
    {
        $env = $inheritCurrent ? getenv() : [];
        
        foreach ($this->items as $secret) {
            $envKey = $secret->sanitizedKey();
            $env[$envKey] = $secret->value() ?? '';
        }
        
        return $env;
    }

    public static function compare(array $vaults, array $envs, ?string $only = null, ?string $except = null): Collection
    {
        $allSecrets = static::gatherSecrets($vaults, $envs, $only, $except);
        $allKeys = static::extractAllKeys($allSecrets);

        return $allKeys->map(function (string $key) use ($allSecrets) {
            $diff = new SecretDiff($key);

            foreach ($allSecrets as $vaultEnv => $secrets) {
                $secret = $secrets->getByKey($key);
                $diff->setValue($vaultEnv, $secret);
            }

            return $diff;
        })->sortBy('key')->values();
    }

    protected static function gatherSecrets(array $vaults, array $envs, ?string $only = null, ?string $except = null): array
    {
        $allSecrets = [];

        foreach ($vaults as $vaultName) {
            foreach ($envs as $env) {
                $vaultEnvKey = "{$vaultName}.{$env}";

                try {
                    $secrets = Keep::vault($vaultName, $env)->list();
                    $allSecrets[$vaultEnvKey] = $secrets->filterByPatterns(only: $only, except: $except);
                } catch (\Exception $e) {
                    $allSecrets[$vaultEnvKey] = new static([]);
                }
            }
        }

        return $allSecrets;
    }

    protected static function extractAllKeys(array $allSecrets): Collection
    {
        $allKeys = collect();

        foreach ($allSecrets as $secrets) {
            $allKeys = $allKeys->merge($secrets->allKeys());
        }

        return $allKeys->unique()->sort()->values();
    }

    public static function generateDiffSummary(Collection $diffs, array $vaults, array $envs): array
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
            'envs_compared' => implode(', ', $envs),
            'vaults_compared' => implode(', ', $vaults),
        ];
    }
}

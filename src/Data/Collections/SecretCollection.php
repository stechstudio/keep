<?php

namespace STS\Keep\Data\Collections;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use STS\Keep\Data\Concerns\FormatsEnvValues;
use STS\Keep\Data\Secret;

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
        $csv = "Key,Value,Vault,Stage,Modified\n";
        
        $this->each(function (Secret $secret) use (&$csv) {
            $key = $this->escapeCsvField($secret->key());
            $value = $this->escapeCsvField($secret->value());
            $vault = $this->escapeCsvField($secret->vault()?->name() ?? '');
            $stage = $this->escapeCsvField($secret->stage() ?? '');
            $modified = $this->escapeCsvField($secret->lastModified()?->toIso8601String() ?? '');
            
            $csv .= "{$key},{$value},{$vault},{$stage},{$modified}\n";
        });
        
        return $csv;
    }

    protected function escapeCsvField(string $field): string
    {
        // If field contains comma, quotes, or newline, wrap in quotes and escape quotes
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

    /**
     * Convert collection to array format suitable for API responses.
     */
    public function toApiArray(bool $unmask = false): array
    {
        return $this->map(fn (Secret $secret) => $secret->toApiArray($unmask))
            ->values()
            ->toArray();
    }
}

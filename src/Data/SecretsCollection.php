<?php

namespace STS\Keep\Data;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SecretsCollection extends Collection
{
    public function toKeyValuePair(): static
    {
        return $this->mapWithKeys(fn(Secret $secret) => [$secret->key() => $secret->value()]);
    }

    public function toEnvString()
    {
        return $this->map(fn(Secret $secret) => $secret->key() . '=' . ($secret->value() !== null ? '"' . addcslashes($secret->value(), '"') . '"' : ''))->implode(PHP_EOL);
    }

    public function filterByPatterns(?string $only = null, ?string $except = null): static
    {
        $onlyPatterns = collect(array_filter(array_map('trim', explode(',', $only))));
        $exceptPatterns = collect(array_filter(array_map('trim', explode(',', $except))));

        return $this->filter(function (Secret $secret) use ($onlyPatterns, $exceptPatterns) {
            $key = $secret->key();

            $matchesOnly = $onlyPatterns->isEmpty() || $onlyPatterns->contains(fn($pattern) => Str::is($pattern, $key, true));
            $matchesExcept = $exceptPatterns->isNotEmpty() && $exceptPatterns->contains(fn($pattern) => Str::is($pattern, $key, true));

            return $matchesOnly && !$matchesExcept;
        });
    }

    public function allKeys(): static
    {
        return $this->map(fn(Secret $secret) => $secret->key())->values();
    }

    public function hasKey(string $key): bool
    {
        return $this->contains(fn(Secret $secret) => $secret->key() === $key);
    }

    public function getByKey(string $key): ?Secret
    {
        return $this->first(fn(Secret $secret) => $secret->key() === $key) ?: null;
    }

    public function toPrettyJson($keys = [])
    {
        return json_encode($this->map->toArray($keys)->all(), JSON_PRETTY_PRINT);
    }
}
<?php

namespace STS\Keeper\Data;

use Illuminate\Support\Collection;

class SecretsCollection extends Collection
{
    public function toKeyValuePair(): static
    {
        return $this->mapWithKeys(fn(Secret $secret) => [$secret->key() => $secret->plainValue()]);
    }
}
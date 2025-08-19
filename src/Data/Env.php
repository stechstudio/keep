<?php

namespace STS\Keep\Data;

use Dotenv\Parser\Parser;
use Illuminate\Support\Collection;
use STS\Keep\Data\Collections\SecretCollection;

class Env
{
    protected Collection $entries;

    public function __construct(protected string $contents) {}

    public function contents(): string
    {
        return $this->contents;
    }

    public function entries(): Collection
    {
        return $this->entries ??= collect((new Parser)->parse($this->contents));
    }

    public function allKeys(): Collection
    {
        return $this->entries()->map->getName()->values();
    }

    public function list(): Collection
    {
        return $this->entries()->mapWithKeys(function ($entry) {
            return [$entry->getName() => $entry->getValue()->get()->getChars()];
        });
    }

    public function secrets(): SecretCollection
    {
        $secrets = $this->entries()->map(function ($entry) {
            return new Secret(
                key: $entry->getName(),
                value: $entry->getValue()->get()->getChars(),
            );
        });

        return new SecretCollection($secrets->all());
    }
}

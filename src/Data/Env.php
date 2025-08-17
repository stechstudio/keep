<?php

namespace STS\Keep\Data;

use Dotenv\Parser\Parser;
use Dotenv\Store\StoreBuilder;
use Illuminate\Support\Collection;

class Env
{
    protected Collection $entries;

    public function __construct(protected string $contents)
    {
    }

    public function contents(): string
    {
        return $this->contents;
    }

    public function entries(): Collection
    {
        return $this->entries ??= collect((new Parser())->parse($this->contents));
    }

    public function allKeys(): Collection
    {
        return $this->entries()->map->getName()->values();
    }
}
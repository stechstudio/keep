<?php

namespace STS\Keeper\Vaults;

use STS\Keeper\Facades\Keeper;
use STS\Keeper\Secret;

abstract class AbstractKeeperVault
{
    protected $keyFormatter;

    public function __construct(protected string $name, protected array $config, protected ?string $environment = null)
    {
        // If none was provided, use the current resolved environment
        if(!$this->environment) {
            $this->environment = Keeper::environment();
        }
    }

    public function forEnvironment(string $environment): static
    {
        $clone = clone $this;
        $clone->environment = $environment;
        return $clone;
    }

    public function formatKeyUsing(callable $formatter): static
    {
        $this->keyFormatter = $formatter;

        return $this;
    }

    abstract public function save(Secret $secret): Secret;

    abstract public function set(string $key, string $value, bool $secure = true);

    abstract public function format(string $key): string;

    public function name(): string
    {
        return $this->name;
    }
}
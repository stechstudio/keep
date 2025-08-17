<?php

namespace STS\Keep\Vaults;

use Illuminate\Support\Str;
use STS\Keep\Data\SecretsCollection;
use STS\Keep\Facades\Keep;
use STS\Keep\Data\Secret;

abstract class AbstractVault
{
    protected $keyFormatter;

    public function __construct(protected string $name, protected array $config, protected ?string $environment = null)
    {
        // If none was provided, use the current resolved environment
        if(!$this->environment) {
            $this->environment = Keep::environment();
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

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return Str::slug($this->name());
    }

    abstract public function list(): SecretsCollection;

    abstract public function get(string $key): Secret;

    abstract public function set(string $key, string $value, bool $secure = true): Secret;

    abstract public function save(Secret $secret): Secret;

    abstract public function format(?string $key = null): string;
}
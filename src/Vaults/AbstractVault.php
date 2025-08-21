<?php

namespace STS\Keep\Vaults;

use Illuminate\Support\Str;
use STS\Keep\Data\Collections\FilterCollection;
use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Collections\SecretHistoryCollection;
use STS\Keep\Data\Secret;

abstract class AbstractVault
{
    public const string DRIVER = '';

    public const string NAME = '';

    protected $keyFormatter;

    public function __construct(protected string $name, protected array $config, protected string $stage) {}

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

    abstract public function list(): SecretCollection;

    abstract public function has(string $key): bool;

    abstract public function get(string $key): Secret;

    abstract public function set(string $key, string $value, bool $secure = true): Secret;

    abstract public function save(Secret $secret): Secret;

    abstract public function delete(string $key): bool;

    abstract public function history(string $key, FilterCollection $filters, ?int $limit = 10): SecretHistoryCollection;
}

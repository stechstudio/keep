<?php

namespace STS\Keep;

use Illuminate\Support\Str;
use InvalidArgumentException;
use STS\Keep\Vaults\AbstractVault;
use STS\Keep\Vaults\AwsSsmVault;

class KeepManager
{
    protected array $vaults = [];

    protected array $customCreators = [];

    protected $stageResolver;

    public function resolveStageUsing(callable $resolver): static
    {
        $this->stageResolver = $resolver;

        return $this;
    }

    public function getDefaultVault()
    {
        return config('keep.default');
    }

    public function available(): array
    {
        return config('keep.available');
    }

    public function stages(): array
    {
        return config('keep.stages');
    }

    public function stage($name = null): string|bool
    {
        if ($name) {
            return $name === $this->stage();
        }

        if (is_callable($this->stageResolver)) {
            return call_user_func($this->stageResolver);
        }

        $stage = config('keep.stage') ?? app()->environment();

        return in_array($stage, $this->stages())
            ? $stage
            : throw new InvalidArgumentException("Stage [{$stage}] is not supported by Keep.");
    }

    public function namespace(): string
    {
        return Str::slug(config('keep.namespace'));
    }

    public function vault($name = null): AbstractVault
    {
        $name = $name ?: $this->getDefaultVault();

        return $this->vaults[$name] ??= $this->resolve($name);
    }

    protected function resolve($name, $config = null): AbstractVault
    {
        $config ??= config("keep.vaults.$name", []);

        if (empty($config['driver'])) {
            throw new InvalidArgumentException("Vault [{$name}] does not have a configured driver.");
        }

        $driver = $config['driver'];

        if (isset($this->customCreators[$driver])) {
            return $this->customCreators[$driver]($name, $config);
        }

        $driverMethod = 'create'.Str::pascal($driver).'Driver';

        if (! method_exists($this, $driverMethod)) {
            throw new InvalidArgumentException("Driver [{$driver}] is not supported.");
        }

        return $this->{$driverMethod}($name, $config);
    }

    public function extend(string $driver, callable $creator): static
    {
        $this->customCreators[$driver] = $creator;

        return $this;
    }

    public function createSsmDriver(string $name, array $config): AwsSsmVault
    {
        return new AwsSsmVault($name, $config);
    }
}

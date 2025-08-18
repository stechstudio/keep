<?php

namespace STS\Keep;

use Illuminate\Support\Str;
use InvalidArgumentException;
use STS\Keep\Vaults\AbstractVault;
use STS\Keep\Vaults\AwsSsmVault;

class KeepManager {

    protected array $vaults = [];
    protected array $customCreators = [];

    protected $environmentResolver;

    public function resolveEnvironmentUsing(callable $resolver): static
    {
        $this->environmentResolver = $resolver;

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

    public function environments(): array
    {
        return config('keep.environments');
    }

    public function environment($name = null): string|bool
    {
        if($name) {
            return $name === $this->environment();
        }

        if(is_callable($this->environmentResolver)) {
            return call_user_func($this->environmentResolver);
        }

        $environment = config('keep.environment') ?? app()->environment();

        return in_array($environment, $this->environments())
            ? $environment
            : throw new InvalidArgumentException("Environment [{$environment}] is not supported by Keep.");
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

        $driverMethod = 'create' . Str::pascal($driver) . 'Driver';

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

    public function createAwsSsmDriver(string $name, array $config): AwsSsmVault
    {
        return new AwsSsmVault($name, $config);
    }
}

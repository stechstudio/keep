<?php

namespace STS\Keeper;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use InvalidArgumentException;
use STS\Keeper\Vaults\AbstractKeeperVault;
use STS\Keeper\Vaults\AwsSsmVault;

class KeeperManager {

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
        return config('keeper.default');
    }

    public function available(): array
    {
        dd("available", config('keeper'));
        return config('keeper.available');
    }

    public function environments(): array
    {
        return config('keeper.environments');
    }

    public function environment($name = null): string|bool
    {
        if($name) {
            return $name === $this->environment();
        }

        if(is_callable($this->environmentResolver)) {
            return call_user_func($this->environmentResolver);
        }

        $environment = config('keeper.environment') ?? app()->environment();

        return in_array($environment, $this->environments())
            ? $environment
            : throw new InvalidArgumentException("Environment [{$environment}] is not supported by Keeper.");
    }

    public function namespace(): string
    {
        return Str::slug(config('keeper.namespace'));
    }

    public function vault($name = null): AbstractKeeperVault
    {
        $name = $name ?: $this->getDefaultVault();

        return $this->vaults[$name] ??= $this->resolve($name);
    }

    protected function resolve($name, $config = null): AbstractKeeperVault
    {
        $config ??= config("keeper.vaults.$name", []);

        if (empty($config['driver'])) {
            throw new InvalidArgumentException("Vault [{$name}] does not have a configured driver.");
        }

        $driver = $config['driver'];

        if (isset($this->customCreators[$driver])) {
            return $this->customCreators[$driver]($config);
        }

        $driverMethod = 'create' . Str::pascal($driver) . 'Driver';

        if (! method_exists($this, $driverMethod)) {
            throw new InvalidArgumentException("Driver [{$driver}] is not supported.");
        }

        return $this->{$driverMethod}($config, $name);
    }

    public function createAwsSsmDriver(array $config): AwsSsmVault
    {
        return new AwsSsmVault($config);
    }
}

<?php

namespace STS\Keeper\Vaults;

use Aws\Ssm\SsmClient;
use Illuminate\Support\Str;
use STS\Keeper\Facades\Keeper;

class AwsSsmVault extends AbstractKeeperVault
{
    protected SsmClient $client;

    public function set(string $key, string $value, bool $secure = true): static
    {
        dd($this->format($key));
        $result = $this->client()->putParameter([
            'Name' => $this->format($key),
            'Value' => $value,
            'Type' => $secure ? 'SecureString' : 'String',
            'Overwrite' => true,
        ]);

        dd($result);

        return $this;
    }

    public function format(string $key): string
    {
        if (is_callable($this->keyFormatter)) {
            return call_user_func($this->keyFormatter, $key, $this->environment, $this->config);
        }

        return Str::of($this->config['prefix'] ?? '')
            ->start('/')->finish('/')
            ->append(Keeper::namespace() . "/")
            ->append($this->environment . "/")
            ->append($key)
            ->toString();
    }

    protected function client(): SsmClient
    {
        return $this->client ??= new SsmClient([
            'version' => 'latest',
            'region' => config('keeper.aws.region'),
        ]);
    }
}
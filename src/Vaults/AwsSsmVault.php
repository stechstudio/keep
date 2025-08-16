<?php

namespace STS\Keeper\Vaults;

use Aws\Result;
use Aws\Ssm\Exception\SsmException;
use Aws\Ssm\SsmClient;
use Illuminate\Support\Str;
use STS\Keeper\Exceptions\AccessDeniedException;
use STS\Keeper\Exceptions\KeeperException;
use STS\Keeper\Exceptions\SecretNotFoundException;
use STS\Keeper\Facades\Keeper;
use STS\Keeper\Secret;

class AwsSsmVault extends AbstractKeeperVault
{
    protected SsmClient $client;

    public function get(string $key): ?Secret
    {
        try {
            $result = $this->client()->getParameter([
                'Name'           => $this->format($key),
                'WithDecryption' => true,
            ]);

            $parameter = $result->get('Parameter');

            if (!$parameter || !is_array($parameter)) {
                return null;
            }

            return new Secret(
                key: $key,
                plainValue: $parameter['Value'] ?? null,
                encryptedValue: null,
                secure: ($parameter['Type'] ?? 'String') === 'SecureString',
                environment: $this->environment,
                version: $parameter['Version'] ?? 0,
            );
        } catch (SsmException $e) {
            dd($e->getAwsErrorCode(), $e->getAwsErrorMessage());
            if($e->getAwsErrorCode() === "ParameterNotFound") {
                throw new SecretNotFoundException($e->getAwsErrorMessage());
            } elseif($e->getAwsErrorCode() === "AccessDeniedException") {
                throw new AccessDeniedException($e->getAwsErrorMessage());
            } else {
                throw new KeeperException($e->getAwsErrorMessage());
            }
        }
    }

    public function save(Secret $secret): Secret
    {
        $result = $this->set($secret->key(), $secret->plainValue(), $secret->isSecure());

        return new Secret(
            key: $secret->key(),
            plainValue: $secret->plainValue(),
            encryptedValue: null,
            secure: $secret->isSecure(),
            environment: $this->environment,
            version: $result->get('Version') ?? 0,
            path: $this->format($secret->key()),
            vault: $this,
        );
    }

    public function set(string $key, string $value, bool $secure = true): Result
    {
        try {
            return $this->client()->putParameter([
                'Name'      => $this->format($key),
                'Value'     => $value,
                'Type'      => $secure ? 'SecureString' : 'String',
                'Overwrite' => true,
            ]);
        } catch (SsmException $e) {
            if($e->getAwsErrorCode() === "AccessDeniedException") {
                throw new AccessDeniedException($e->getAwsErrorMessage());
            } else {
                throw new KeeperException($e->getAwsErrorMessage());
            }
        }
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
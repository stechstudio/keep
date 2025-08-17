<?php

namespace STS\Keeper\Vaults;

use Aws\Result;
use Aws\Ssm\Exception\SsmException;
use Aws\Ssm\SsmClient;
use Illuminate\Support\Str;
use STS\Keeper\Data\SecretsCollection;
use STS\Keeper\Exceptions\AccessDeniedException;
use STS\Keeper\Exceptions\KeeperException;
use STS\Keeper\Exceptions\SecretNotFoundException;
use STS\Keeper\Facades\Keeper;
use STS\Keeper\Data\Secret;

class AwsSsmVault extends AbstractKeeperVault
{
    protected SsmClient $client;

    public function format(?string $key = null): string
    {
        if (is_callable($this->keyFormatter)) {
            return call_user_func($this->keyFormatter, $key, $this->environment, $this->config);
        }

        return Str::of($this->config['prefix'] ?? '')
            ->start('/')->finish('/')
            ->append(Keeper::namespace() . "/")
            ->append($this->environment . "/")
            ->append($key)
            ->rtrim("/")
            ->toString();
    }

    public function list(): SecretsCollection
    {
        try {
            $secrets = new SecretsCollection();
            $nextToken = null;

            do {
                $params = [
                    'Path'           => $this->format(),
                    'Recursive'      => true,
                    'WithDecryption' => true,
                    'MaxResults'     => 10,
                ];

                if ($nextToken) {
                    $params['NextToken'] = $nextToken;
                }

                $result = $this->client()->getParametersByPath($params);

                foreach ($result->get('Parameters') as $parameter) {
                    $key = Str::of($parameter['Name'])
                        ->replace($this->format(), '')
                        ->trim('/')
                        ->toString();

                    $secrets->push(new Secret(
                        key: $key,
                        plainValue: $parameter['Value'] ?? null,
                        encryptedValue: null,
                        secure: ($parameter['Type'] ?? 'String') === 'SecureString',
                        environment: $this->environment,
                        version: $parameter['Version'] ?? 0,
                        path: $parameter['Name'],
                        //vault: $this,
                    ));
                }
            } while ($nextToken = $result->get('NextToken'));

        } catch (SsmException $e) {
            if($e->getAwsErrorCode() === "AccessDeniedException") {
                throw new AccessDeniedException($e->getAwsErrorMessage());
            } else {
                throw new KeeperException($e->getAwsErrorMessage());
            }
        }

        return $secrets->sortBy('key')->values();
    }

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
            if($e->getAwsErrorCode() === "ParameterNotFound") {
                throw new SecretNotFoundException("Secret not found");
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

    protected function client(): SsmClient
    {
        return $this->client ??= new SsmClient([
            'version' => 'latest',
            'region' => config('keeper.aws.region'),
        ]);
    }
}
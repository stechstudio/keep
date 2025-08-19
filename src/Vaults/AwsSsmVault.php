<?php

namespace STS\Keep\Vaults;

use Aws\Ssm\Exception\SsmException;
use Aws\Ssm\SsmClient;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use STS\Keep\Data\Secret;
use STS\Keep\Data\SecretHistory;
use STS\Keep\Data\SecretsCollection;
use STS\Keep\Exceptions\AccessDeniedException;
use STS\Keep\Exceptions\KeepException;
use STS\Keep\Exceptions\SecretNotFoundException;
use STS\Keep\Facades\Keep;

class AwsSsmVault extends AbstractVault
{
    protected SsmClient $client;

    public function format(?string $key = null): string
    {
        if (is_callable($this->keyFormatter)) {
            return call_user_func($this->keyFormatter, $key, $this->environment, $this->config);
        }

        return Str::of($this->config['prefix'] ?? '')
            ->start('/')->finish('/')
            ->append(Keep::namespace().'/')
            ->append($this->environment.'/')
            ->append($key)
            ->rtrim('/')
            ->toString();
    }

    public function list(): SecretsCollection
    {
        try {
            $secrets = new SecretsCollection;
            $nextToken = null;

            do {
                $params = [
                    'Path' => $this->format(),
                    'Recursive' => true,
                    'WithDecryption' => true,
                    'MaxResults' => 10,
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
                        value: $parameter['Value'] ?? null,
                        encryptedValue: null,
                        secure: ($parameter['Type'] ?? 'String') === 'SecureString',
                        environment: $this->environment,
                        revision: $parameter['Version'] ?? 0,
                        path: $parameter['Name'],
                        vault: $this,
                    ));
                }
            } while ($nextToken = $result->get('NextToken'));

        } catch (SsmException $e) {
            if ($e->getAwsErrorCode() === 'AccessDeniedException') {
                throw new AccessDeniedException($e->getAwsErrorMessage());
            } else {
                throw new KeepException($e->getAwsErrorMessage());
            }
        }

        return $secrets->sortBy('key')->values();
    }

    public function get(string $key): Secret
    {
        try {
            $result = $this->client()->getParameter([
                'Name' => $this->format($key),
                'WithDecryption' => true,
            ]);

            $parameter = $result->get('Parameter');

            if (! $parameter || ! is_array($parameter)) {
                throw new SecretNotFoundException("Secret not found [{$this->format($key)}]");
            }

            return new Secret(
                key: $key,
                value: $parameter['Value'] ?? null,
                encryptedValue: null,
                secure: ($parameter['Type'] ?? 'String') === 'SecureString',
                environment: $this->environment,
                revision: $parameter['Version'] ?? 0,
                path: $parameter['Name'],
                vault: $this,
            );
        } catch (SsmException $e) {
            if ($e->getAwsErrorCode() === 'ParameterNotFound') {
                throw new SecretNotFoundException("Secret not found [{$this->format($key)}]");
            } elseif ($e->getAwsErrorCode() === 'AccessDeniedException') {
                throw new AccessDeniedException($e->getAwsErrorMessage());
            } else {
                throw new KeepException($e->getAwsErrorMessage());
            }
        }
    }

    public function save(Secret $secret): Secret
    {
        return $this->set($secret->key(), $secret->value(), $secret->isSecure());
    }

    public function set(string $key, string $value, bool $secure = true): Secret
    {
        try {
            $this->client()->putParameter([
                'Name' => $this->format($key),
                'Value' => $value,
                'Type' => $secure ? 'SecureString' : 'String',
                'Overwrite' => true,
            ]);

            return $this->get($key);
        } catch (SsmException $e) {
            if ($e->getAwsErrorCode() === 'AccessDeniedException') {
                throw new AccessDeniedException($e->getAwsErrorMessage());
            } else {
                throw new KeepException($e->getAwsErrorMessage());
            }
        }
    }

    public function delete(string $key): bool
    {
        try {
            $this->client()->deleteParameter([
                'Name' => $this->format($key),
            ]);

            return true;
        } catch (SsmException $e) {
            if ($e->getAwsErrorCode() === 'ParameterNotFound') {
                throw new SecretNotFoundException("Secret [{$key}] not found in vault [{$this->name()}]");
            } elseif ($e->getAwsErrorCode() === 'AccessDeniedException') {
                throw new AccessDeniedException($e->getAwsErrorMessage());
            } else {
                throw new KeepException($e->getAwsErrorMessage());
            }
        }
    }

    public function history(string $key, int $limit = 10): Collection
    {
        try {
            $result = $this->client()->getParameterHistory([
                'Name' => $this->format($key),
                'WithDecryption' => true,
                'MaxResults' => $limit,
            ]);

            $parameters = $result->get('Parameters') ?? [];

            return collect($parameters)->map(function ($parameter) use ($key) {
                return new SecretHistory(
                    key: $key,
                    value: $parameter['Value'] ?? null,
                    version: $parameter['Version'] ?? 1,
                    lastModifiedDate: $parameter['LastModifiedDate'] ? Carbon::parse($parameter['LastModifiedDate']) : null,
                    lastModifiedUser: $parameter['LastModifiedUser'] ?? null,
                    dataType: $parameter['DataType'] ?? null,
                    labels: $parameter['Labels'] ?? [],
                    policies: $parameter['Policies'] ?? null,
                    description: $parameter['Description'] ?? null,
                    secure: ($parameter['Type'] ?? 'String') === 'SecureString',
                );
            })->sortByDesc('version')->values();
        } catch (SsmException $e) {
            if ($e->getAwsErrorCode() === 'ParameterNotFound') {
                throw new SecretNotFoundException("Secret [{$key}] not found in vault [{$this->name()}]");
            } elseif ($e->getAwsErrorCode() === 'AccessDeniedException') {
                throw new AccessDeniedException($e->getAwsErrorMessage());
            } else {
                throw new KeepException($e->getAwsErrorMessage());
            }
        }
    }

    protected function client(): SsmClient
    {
        return $this->client ??= new SsmClient([
            'version' => 'latest',
            'region' => config('keep.aws.region'),
        ]);
    }
}

<?php

namespace STS\Keep\Vaults;

use Aws\Ssm\Exception\SsmException;
use Aws\Ssm\SsmClient;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Prompts\TextPrompt;
use STS\Keep\Data\Collections\FilterCollection;
use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Collections\SecretHistoryCollection;
use STS\Keep\Data\Secret;
use STS\Keep\Data\SecretHistory;
use STS\Keep\Exceptions\AccessDeniedException;
use STS\Keep\Exceptions\ExceptionFactory;
use STS\Keep\Exceptions\KeepException;
use STS\Keep\Exceptions\SecretNotFoundException;
use STS\Keep\Facades\Keep;

class AwsSsmVault extends AbstractVault
{
    public const string DRIVER = 'ssm';

    public const string NAME = 'AWS SSM Parameter Store';

    protected SsmClient $client;

    public static function configure(array $existingSettings = []): array
    {
        return [
            'region' => new TextPrompt(
                label: 'AWS Region',
                default: $existingSettings['region'] ?? 'us-east-1',
                hint: 'The AWS region where your parameters will be stored'
            ),
            'prefix' => new TextPrompt(
                label: 'Parameter prefix (optional)',
                default: $existingSettings['prefix'] ?? '',
                hint: 'Base path for all your parameters (e.g., /[prefix]/myapp/production/DB_PASSWORD)'
            ),
            'key' => new TextPrompt(
                label: 'KMS Key ID (optional)',
                default: $existingSettings['key'] ?? '',
                hint: 'Leave empty to use the default AWS managed key'
            ),
        ];
    }

    public function format(?string $key = null): string
    {
        return Str::of($this->config['prefix'] ?? '')
            ->start('/')->finish('/')
            ->append(Keep::getNamespace().'/')
            ->append($this->stage.'/')
            ->append($key)
            ->rtrim('/')
            ->toString();
    }

    public function list(): SecretCollection
    {
        try {
            $secrets = new SecretCollection;
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

                    $secrets->push(Secret::fromVault(
                        key: $key,
                        value: $parameter['Value'] ?? null,
                        encryptedValue: null,
                        secure: ($parameter['Type'] ?? 'String') === 'SecureString',
                        stage: $this->stage,
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

        return $secrets->sorted();
    }

    public function has(string $key): bool
    {
        try {
            return $this->get($key) instanceof Secret;
        } catch (SecretNotFoundException $e) {
            return false;
        }
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
                throw ExceptionFactory::secretNotFound($key, $this->name());
            }

            return Secret::fromVault(
                key: $key,
                value: $parameter['Value'] ?? null,
                encryptedValue: null,
                secure: ($parameter['Type'] ?? 'String') === 'SecureString',
                stage: $this->stage,
                revision: $parameter['Version'] ?? 0,
                path: $parameter['Name'],
                vault: $this,
            );
        } catch (SsmException $e) {
            if ($e->getAwsErrorCode() === 'ParameterNotFound') {
                throw ExceptionFactory::secretNotFound($key, $this->name());
            } elseif ($e->getAwsErrorCode() === 'AccessDeniedException') {
                throw new AccessDeniedException($e->getAwsErrorMessage());
            } else {
                throw ExceptionFactory::awsError($e->getAwsErrorMessage(), $this->name(), $key);
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
            $this->client()->putParameter(Arr::whereNotNull([
                'Name' => $this->format($key),
                'Value' => $value,
                'Type' => $secure ? 'SecureString' : 'String',
                'Overwrite' => true,
                'KeyId' => $this->config['key'] ?? null,
            ]));

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

    public function history(string $key, FilterCollection $filters, ?int $limit = 10): SecretHistoryCollection
    {
        // AWS doesn't provide query-time filtering for parameter history, we have to fetch all and filter manually
        $fetchAll = $filters->isNotEmpty();

        try {
            $histories = new SecretHistoryCollection;
            $nextToken = null;

            do {
                $params = [
                    'Name' => $this->format($key),
                    'WithDecryption' => true,
                    'MaxResults' => 50, // AWS max for getParameterHistory
                ];

                if ($nextToken) {
                    $params['NextToken'] = $nextToken;
                }

                // If not fetching all and we have a limit, adjust MaxResults to avoid over-fetching
                if (! $fetchAll && $limit) {
                    $remaining = $limit - $histories->count();
                    if ($remaining <= 0) {
                        break;
                    }
                    $params['MaxResults'] = min(50, $remaining);
                }

                $result = $this->client()->getParameterHistory($params);
                $parameters = $result->get('Parameters') ?? [];

                foreach ($parameters as $parameter) {
                    $histories->push(new SecretHistory(
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
                    ));

                    // Break early if we have enough and not fetching all
                    if (! $fetchAll && $limit && $histories->count() >= $limit) {
                        break 2;
                    }
                }

                $nextToken = $result->get('NextToken');

                // Continue only if there's more data and we either want all or haven't hit our limit
            } while ($nextToken && ($fetchAll || ! $limit || $histories->count() < $limit));

            $histories = $histories->applyFilters($filters)->sortByVersionDesc();

            // If we have a limit, slice the collection to that limit
            return $limit !== null ? $histories->take($limit) : $histories;

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
            'region' => $this->config['region'] ?? 'us-east-1',
            'use_aws_shared_config_files' => true,
        ]);
    }
}

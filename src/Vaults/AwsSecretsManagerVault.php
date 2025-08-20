<?php

namespace STS\Keep\Vaults;

use Aws\SecretsManager\Exception\SecretsManagerException;
use Aws\SecretsManager\SecretsManagerClient;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use STS\Keep\Data\Collections\FilterCollection;
use STS\Keep\Data\Secret;
use STS\Keep\Data\SecretHistory;
use STS\Keep\Data\Collections\SecretHistoryCollection;
use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Exceptions\AccessDeniedException;
use STS\Keep\Exceptions\KeepException;
use STS\Keep\Exceptions\SecretNotFoundException;
use STS\Keep\Facades\Keep;
use STS\Keep\Prompts\TextPrompt;

class AwsSecretsManagerVault extends AbstractVault
{
    public const string DRIVER = 'secretsmanager';
    public const string NAME = 'AWS Secrets Manager';

    protected SecretsManagerClient $client;
    
    public static function configure(array $existingSettings = []): array
    {
        return [
            'region' => new TextPrompt(
                label: 'AWS Region',
                default: $existingSettings['region'] ?? 'us-east-1',
                hint: 'The AWS region where your secrets will be stored'
            ),
            'prefix' => new TextPrompt(
                label: 'Secret name prefix',
                default: $existingSettings['prefix'] ?? 'app-secrets',
                hint: 'Prefix for all your secret names'
            ),
            'key' => new TextPrompt(
                label: 'KMS Key ID (optional)',
                default: $existingSettings['key'] ?? '',
                hint: 'Leave empty to use the default AWS managed key'
            )
        ];
    }

    public function format(?string $key = null): string
    {
        if (is_callable($this->keyFormatter)) {
            return call_user_func($this->keyFormatter, $key, $this->stage, $this->config);
        }

        return Str::of($this->config['prefix'] ?? '')
            ->append('/')
            ->append('app') // TODO: Get from configuration
            ->append('/')
            ->append($this->stage)
            ->append('/')
            ->append($key)
            ->replace('//', '/')
            ->trim('/')
            ->toString();
    }

    public function list(): SecretCollection
    {
        try {
            $secrets = new SecretCollection;
            $nextToken = null;
            $prefix = $this->format();

            do {
                $params = [
                    'MaxResults' => 100, // AWS Secrets Manager max
                    'Filters' => [
                        [
                            'Key' => 'name',
                            'Values' => [$prefix . '*']
                        ]
                    ],
                    'IncludePlannedDeletion' => false,
                ];

                if ($nextToken) {
                    $params['NextToken'] = $nextToken;
                }

                $result = $this->client()->listSecrets($params);

                foreach ($result->get('SecretList') as $secret) {
                    $secretName = $secret['Name'];
                    
                    // Extract the key from the full secret name
                    $key = Str::of($secretName)
                        ->after($prefix . '/')
                        ->toString();

                    // Skip if this doesn't match our prefix pattern exactly
                    if (!Str::startsWith($secretName, $prefix . '/')) {
                        continue;
                    }

                    // Get the actual secret value
                    try {
                        $secretValue = $this->client()->getSecretValue([
                            'SecretId' => $secretName,
                        ]);

                        $value = $secretValue->get('SecretString');
                        $isSecure = true; // AWS Secrets Manager always encrypts

                        $secrets->push(new Secret(
                            key: $key,
                            value: $value,
                            encryptedValue: null,
                            secure: $isSecure,
                            stage: $this->stage,
                            revision: intval($secret['VersionId'] ?? 1),
                            path: $secretName,
                            vault: $this,
                        ));
                    } catch (SecretsManagerException $e) {
                        // Skip secrets we can't read (permission issues, etc.)
                        continue;
                    }
                }

                $nextToken = $result->get('NextToken');
            } while ($nextToken);

        } catch (SecretsManagerException $e) {
            if ($e->getAwsErrorCode() === 'AccessDeniedException') {
                throw new AccessDeniedException($e->getAwsErrorMessage());
            } else {
                throw new KeepException($e->getAwsErrorMessage());
            }
        }

        return $secrets->sortBy('key')->values();
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
            $result = $this->client()->getSecretValue([
                'SecretId' => $this->format($key),
            ]);

            $value = $result->get('SecretString');
            $versionId = $result->get('VersionId');
            
            if ($value === null) {
                throw new SecretNotFoundException("Secret not found [{$this->format($key)}]");
            }

            return new Secret(
                key: $key,
                value: $value,
                encryptedValue: null,
                secure: true, // AWS Secrets Manager always encrypts
                stage: $this->stage,
                revision: intval($versionId ?? 1),
                path: $this->format($key),
                vault: $this,
            );
        } catch (SecretsManagerException $e) {
            if ($e->getAwsErrorCode() === 'ResourceNotFoundException') {
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
            $secretName = $this->format($key);
            
            // Check if secret exists
            $exists = false;
            try {
                $this->client()->describeSecret(['SecretId' => $secretName]);
                $exists = true;
            } catch (SecretsManagerException $e) {
                if ($e->getAwsErrorCode() !== 'ResourceNotFoundException') {
                    throw $e;
                }
            }

            if ($exists) {
                // Update existing secret
                $this->client()->updateSecret([
                    'SecretId' => $secretName,
                    'SecretString' => $value,
                ]);
            } else {
                // Create new secret
                $this->client()->createSecret([
                    'Name' => $secretName,
                    'SecretString' => $value,
                    'Description' => "Keep secret: {$key} for stage {$this->stage}",
                    'KmsKeyId' => $this->config['key'] ?? null,
                ]);
            }

            return $this->get($key);
        } catch (SecretsManagerException $e) {
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
            $this->client()->deleteSecret([
                'SecretId' => $this->format($key),
                'ForceDeleteWithoutRecovery' => true, // Immediate deletion
            ]);

            return true;
        } catch (SecretsManagerException $e) {
            if ($e->getAwsErrorCode() === 'ResourceNotFoundException') {
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
        try {
            $histories = new SecretHistoryCollection;
            $secretName = $this->format($key);

            // Get secret versions
            $result = $this->client()->listSecretVersionIds([
                'SecretId' => $secretName,
                'MaxResults' => $limit ?? 100,
                'IncludeDeprecated' => true,
            ]);

            $versions = $result->get('Versions') ?? [];
            
            foreach ($versions as $version) {
                try {
                    // Get the actual secret value for this version
                    $secretValue = $this->client()->getSecretValue([
                        'SecretId' => $secretName,
                        'VersionId' => $version['VersionId'],
                    ]);

                    $histories->push(new SecretHistory(
                        key: $key,
                        value: $secretValue->get('SecretString'),
                        version: intval($version['VersionId'] ?? 1),
                        lastModifiedDate: isset($version['CreatedDate']) ? Carbon::parse($version['CreatedDate']) : null,
                        lastModifiedUser: null, // AWS Secrets Manager doesn't track user info
                        dataType: 'SecretString',
                        labels: $version['VersionStages'] ?? [],
                        policies: null,
                        description: null,
                        secure: true, // AWS Secrets Manager always encrypts
                    ));
                } catch (SecretsManagerException $e) {
                    // Skip versions we can't access
                    continue;
                }
            }

            $histories = $histories->applyFilters($filters)->sortByVersionDesc();

            // If we have a limit, slice the collection to that limit
            return $limit !== null ? $histories->take($limit) : $histories;
            
        } catch (SecretsManagerException $e) {
            if ($e->getAwsErrorCode() === 'ResourceNotFoundException') {
                throw new SecretNotFoundException("Secret [{$key}] not found in vault [{$this->name()}]");
            } elseif ($e->getAwsErrorCode() === 'AccessDeniedException') {
                throw new AccessDeniedException($e->getAwsErrorMessage());
            } else {
                throw new KeepException($e->getAwsErrorMessage());
            }
        }
    }

    protected function client(): SecretsManagerClient
    {
        return $this->client ??= new SecretsManagerClient([
            'version' => 'latest',
            'region' => config('keep.aws.region'),
        ]);
    }
}
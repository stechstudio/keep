<?php

namespace STS\Keep\Vaults;

use Aws\SecretsManager\Exception\SecretsManagerException;
use Aws\SecretsManager\SecretsManagerClient;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Prompts\TextPrompt;
use STS\Keep\Data\Collections\FilterCollection;
use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Collections\SecretHistoryCollection;
use STS\Keep\Data\Secret;
use STS\Keep\Data\SecretHistory;
use STS\Keep\Exceptions\AccessDeniedException;
use STS\Keep\Exceptions\KeepException;
use STS\Keep\Exceptions\SecretNotFoundException;
use STS\Keep\Facades\Keep;

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
            'key' => new TextPrompt(
                label: 'KMS Key ID (optional)',
                default: $existingSettings['key'] ?? '',
                hint: 'Leave empty to use the default AWS managed key'
            ),
            'scope' => new TextPrompt(
                label: 'Scope (optional)',
                default: $existingSettings['scope'] ?? '',
                hint: 'Optional scope to isolate secrets within namespace (e.g., "app2" for namespace/app2/stage/key)'
            ),
        ];
    }

    /**
     * Format a secret name using path-style naming for duplicate avoidance only.
     */
    public function format(?string $key = null): string
    {
        return Str::of('')
            ->when(Keep::getNamespace(), fn($str) => $str->append(Keep::getNamespace().'/'))
            ->when(trim($this->config['scope'] ?? '', '/'), fn($str, $scope) => $str->append($scope.'/'))
            ->append($this->stage)
            ->when($key, fn($str) => $str->append('/'.$key))
            ->trim('/')
            ->toString();
    }

    /**
     * Get the standard tags applied to all secrets in this vault.
     */
    protected function getSecretTags(): array
    {
        $tags = [
            'ManagedBy' => 'Keep',
            'Namespace' => Keep::getNamespace(),
            'Stage' => $this->stage,
            'VaultSlug' => $this->slug(),
        ];
        
        // Add scope tag if configured
        $scope = $this->config['scope'] ?? '';
        if ($scope) {
            $scope = trim($scope, '/');
            if ($scope) {
                $tags['Scope'] = $scope;
            }
        }
        
        return $tags;
    }

    /**
     * List secrets using tag-based filtering.
     */
    public function list(): SecretCollection
    {
        try {
            $secrets = new SecretCollection;
            $nextToken = null;

            do {
                $filters = [
                    [
                        'Key' => 'tag-key',
                        'Values' => ['ManagedBy'],
                    ],
                    [
                        'Key' => 'tag-value',
                        'Values' => ['Keep'],
                    ],
                    [
                        'Key' => 'tag-key',
                        'Values' => ['Namespace'],
                    ],
                    [
                        'Key' => 'tag-value',
                        'Values' => [Keep::getNamespace()],
                    ],
                    [
                        'Key' => 'tag-key',
                        'Values' => ['Stage'],
                    ],
                    [
                        'Key' => 'tag-value',
                        'Values' => [$this->stage],
                    ],
                ];
                
                // Add scope filter if configured
                $scope = $this->config['scope'] ?? '';
                if ($scope) {
                    $scope = trim($scope, '/');
                    if ($scope) {
                        $filters[] = [
                            'Key' => 'tag-key',
                            'Values' => ['Scope'],
                        ];
                        $filters[] = [
                            'Key' => 'tag-value',
                            'Values' => [$scope],
                        ];
                    }
                }
                
                $params = [
                    'MaxResults' => 20,
                    'Filters' => $filters,
                    'IncludePlannedDeletion' => false,
                ];

                if ($nextToken) {
                    $params['NextToken'] = $nextToken;
                }

                // First list secrets matching our filters
                $listResult = $this->client()->listSecrets($params);
                
                // Get the actual secret values for the listed secrets
                $secretList = $listResult->get('SecretList');
                if (empty($secretList)) {
                    break;
                }
                
                // Collect secret ARNs for batch retrieval
                $secretIds = [];
                foreach ($secretList as $secretMeta) {
                    $secretIds[] = $secretMeta['ARN'];
                }
                
                // Batch get the secret values
                $valueResult = $this->client()->batchGetSecretValue([
                    'SecretIdList' => $secretIds
                ]);

                foreach ($valueResult->get('SecretValues') as $secret) {
                    $secretName = $secret['Name'];

                    // Extract the key from the full secret name using the expected format
                    // Format: namespace/[scope/]stage/key
                    $basePath = $this->format(); // Gets the base path without the key
                    
                    // Skip if this doesn't match our expected naming pattern
                    if (! Str::startsWith($secretName, $basePath . '/')) {
                        continue;
                    }

                    $key = Str::of($secretName)
                        ->after($basePath . '/')
                        ->toString();

                    // Skip if we couldn't extract a valid key
                    if (empty($key)) {
                        continue;
                    }

                    $lastModified = null;
                    if (isset($secret['CreatedDate'])) {
                        $lastModified = Carbon::parse($secret['CreatedDate']);
                    }

                    $secrets->push(Secret::fromVault(
                        key: $key,
                        value: $secret['SecretString'] ?? null,
                        encryptedValue: null,
                        secure: true,
                        stage: $this->stage,
                        revision: $secret['VersionId'] ?? 1,
                        path: $secretName,
                        vault: $this,
                        lastModified: $lastModified,
                    ));
                }

                $nextToken = $listResult->get('NextToken');
            } while ($nextToken);

        } catch (SecretsManagerException $e) {
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
            return $this->list()->getByPath($key) instanceof Secret;
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

            $lastModified = null;
            if ($createdDate = $result->get('CreatedDate')) {
                $lastModified = Carbon::parse($createdDate);
            }

            return Secret::fromVault(
                key: $key,
                value: $value,
                encryptedValue: null,
                secure: true, // AWS Secrets Manager always encrypts
                stage: $this->stage,
                revision: $versionId ?? 1,
                path: $this->format($key),
                vault: $this,
                lastModified: $lastModified,
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
            $tags = $this->getSecretTags();

            // Check if secret exists
            $exists = false;
            try {
                $exists = $this->has($secretName);
            } catch (SecretsManagerException $e) {
                if ($e->getAwsErrorCode() !== 'ResourceNotFoundException') {
                    throw $e;
                }
            }

            if ($exists) {
                // Update existing secret value
                $this->client()->putSecretValue([
                    'SecretId' => $secretName,
                    'SecretString' => $value,
                ]);

                // Update tags to ensure they're current
                $this->client()->tagResource([
                    'SecretId' => $secretName,
                    'Tags' => collect($tags)->map(fn ($value, $key) => [
                        'Key' => $key,
                        'Value' => $value,
                    ])->values()->toArray(),
                ]);
            } else {
                // Create new secret with tags
                $createParams = [
                    'Name' => $secretName,
                    'SecretString' => $value,
                    'Description' => "Keep secret: {$key} for {$this->stage} stage in {$this->slug()} vault",
                    'Tags' => collect($tags)->map(fn ($value, $key) => [
                        'Key' => $key,
                        'Value' => $value,
                    ])->values()->toArray(),
                ];

                if (! empty($this->config['key'])) {
                    $createParams['KmsKeyId'] = $this->config['key'];
                }

                $this->client()->createSecret($createParams);
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
                        version: $version['VersionId'] ?? 1,
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
            'region' => $this->config['region'] ?? 'us-east-1',
            'use_aws_shared_config_files' => true,
        ]);
    }
}

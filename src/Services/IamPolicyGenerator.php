<?php

namespace STS\Keep\Services;

use Illuminate\Support\Collection;
use STS\Keep\Data\VaultConfig;

class IamPolicyGenerator
{
    public function generate(Collection $vaults, string $namespace, array $envs = []): array
    {
        $statements = [];

        $ssmVaults = $vaults->filter(fn (VaultConfig $v) => $v->driver() === 'ssm');
        $smVaults = $vaults->filter(fn (VaultConfig $v) => $v->driver() === 'secretsmanager');

        if ($ssmVaults->isNotEmpty()) {
            $statements = array_merge($statements, $this->ssmStatements($ssmVaults, $namespace, $envs));
        }

        if ($smVaults->isNotEmpty()) {
            $statements = array_merge($statements, $this->secretsManagerStatements($smVaults, $namespace, $envs));
        }

        return [
            'Version' => '2012-10-17',
            'Statement' => $statements,
        ];
    }

    protected function ssmStatements(Collection $vaults, string $namespace, array $envs): array
    {
        $resources = [];
        $kmsResources = [];

        foreach ($vaults as $vault) {
            $region = $vault->get('region', '*');
            $scope = trim($vault->scope(), '/');
            $base = $scope ? "{$namespace}/{$scope}" : $namespace;

            if (empty($envs)) {
                $resources[] = "arn:aws:ssm:{$region}:*:parameter/{$base}/*";
            } else {
                foreach ($envs as $env) {
                    $resources[] = "arn:aws:ssm:{$region}:*:parameter/{$base}/{$env}/*";
                }
            }

            $customKey = $vault->get('key');
            $kmsResources[] = $customKey ?: "arn:aws:kms:{$region}:*:alias/aws/ssm";
        }

        $resources = array_values(array_unique($resources));
        $kmsResources = array_values(array_unique($kmsResources));

        return [
            [
                'Sid' => 'KeepSsmAccess',
                'Effect' => 'Allow',
                'Action' => [
                    'ssm:GetParameter',
                    'ssm:GetParameters',
                    'ssm:GetParametersByPath',
                    'ssm:GetParameterHistory',
                    'ssm:PutParameter',
                    'ssm:DeleteParameter',
                ],
                'Resource' => count($resources) === 1 ? $resources[0] : $resources,
            ],
            [
                'Sid' => 'KeepSsmKms',
                'Effect' => 'Allow',
                'Action' => [
                    'kms:Decrypt',
                    'kms:Encrypt',
                    'kms:GenerateDataKey',
                ],
                'Resource' => count($kmsResources) === 1 ? $kmsResources[0] : $kmsResources,
            ],
        ];
    }

    protected function secretsManagerStatements(Collection $vaults, string $namespace, array $envs): array
    {
        $kmsResources = [];

        foreach ($vaults as $vault) {
            $region = $vault->get('region', '*');
            $customKey = $vault->get('key');
            $kmsResources[] = $customKey ?: "arn:aws:kms:{$region}:*:alias/aws/secretsmanager";
        }

        $kmsResources = array_values(array_unique($kmsResources));

        $tagKeys = ['ManagedBy', 'Namespace', 'Env', 'VaultSlug'];

        $hasScope = $vaults->contains(fn (VaultConfig $v) => trim($v->scope(), '/') !== '');
        if ($hasScope) {
            $tagKeys[] = 'Scope';
        }

        $readWriteCondition = [
            'StringEquals' => [
                'secretsmanager:ResourceTag/Namespace' => $namespace,
            ],
        ];

        $createCondition = [
            'StringEquals' => [
                'aws:RequestTag/Namespace' => $namespace,
            ],
            'ForAllValues:StringEquals' => [
                'aws:TagKeys' => $tagKeys,
            ],
        ];

        if (! empty($envs)) {
            $envValue = count($envs) === 1 ? $envs[0] : $envs;

            $readWriteCondition['ForAnyValue:StringEquals'] = [
                'secretsmanager:ResourceTag/Env' => $envValue,
            ];

            $createCondition['ForAnyValue:StringEquals'] = [
                'aws:RequestTag/Env' => $envValue,
            ];
        }

        return [
            [
                'Sid' => 'KeepSecretsManagerReadWrite',
                'Effect' => 'Allow',
                'Action' => [
                    'secretsmanager:GetSecretValue',
                    'secretsmanager:DescribeSecret',
                    'secretsmanager:ListSecretVersionIds',
                    'secretsmanager:PutSecretValue',
                    'secretsmanager:UpdateSecret*',
                    'secretsmanager:DeleteSecret',
                    'secretsmanager:RestoreSecret',
                    'secretsmanager:TagResource',
                    'secretsmanager:UntagResource',
                ],
                'Resource' => '*',
                'Condition' => $readWriteCondition,
            ],
            [
                'Sid' => 'KeepSecretsManagerList',
                'Effect' => 'Allow',
                'Action' => [
                    'secretsmanager:ListSecrets',
                    'secretsmanager:BatchGetSecretValue',
                ],
                'Resource' => '*',
            ],
            [
                'Sid' => 'KeepSecretsManagerCreate',
                'Effect' => 'Allow',
                'Action' => 'secretsmanager:CreateSecret',
                'Resource' => '*',
                'Condition' => $createCondition,
            ],
            [
                'Sid' => 'KeepSecretsManagerKms',
                'Effect' => 'Allow',
                'Action' => [
                    'kms:Decrypt',
                    'kms:Encrypt',
                    'kms:GenerateDataKey',
                ],
                'Resource' => count($kmsResources) === 1 ? $kmsResources[0] : $kmsResources,
            ],
        ];
    }
}

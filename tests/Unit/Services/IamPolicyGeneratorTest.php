<?php

use STS\Keep\Data\Collections\VaultConfigCollection;
use STS\Keep\Data\VaultConfig;
use STS\Keep\Services\IamPolicyGenerator;

beforeEach(function () {
    $this->generator = new IamPolicyGenerator();
});

it('generates SSM policy with correct resource ARN', function () {
    $vaults = new VaultConfigCollection([
        'ssm' => VaultConfig::fromArray([
            'slug' => 'ssm',
            'driver' => 'ssm',
            'name' => 'SSM Vault',
            'region' => 'us-east-1',
        ]),
    ]);

    $policy = $this->generator->generate($vaults, 'myapp');

    expect($policy['Version'])->toBe('2012-10-17');
    expect($policy['Statement'])->toHaveCount(2);

    $ssmStatement = $policy['Statement'][0];
    expect($ssmStatement['Sid'])->toBe('KeepSsmAccess');
    expect($ssmStatement['Resource'])->toBe('arn:aws:ssm:us-east-1:*:parameter/myapp/*');
    expect($ssmStatement['Action'])->toContain('ssm:GetParameter', 'ssm:PutParameter', 'ssm:DeleteParameter');

    $kmsStatement = $policy['Statement'][1];
    expect($kmsStatement['Sid'])->toBe('KeepSsmKms');
    expect($kmsStatement['Resource'])->toBe('arn:aws:kms:us-east-1:*:alias/aws/ssm');
});

it('generates SSM policy with scope in resource ARN', function () {
    $vaults = new VaultConfigCollection([
        'ssm' => VaultConfig::fromArray([
            'slug' => 'ssm',
            'driver' => 'ssm',
            'name' => 'SSM Vault',
            'scope' => 'app2',
            'region' => 'us-west-2',
        ]),
    ]);

    $policy = $this->generator->generate($vaults, 'myapp');

    expect($policy['Statement'][0]['Resource'])->toBe('arn:aws:ssm:us-west-2:*:parameter/myapp/app2/*');
});

it('generates SSM policy with custom KMS key', function () {
    $vaults = new VaultConfigCollection([
        'ssm' => VaultConfig::fromArray([
            'slug' => 'ssm',
            'driver' => 'ssm',
            'name' => 'SSM Vault',
            'region' => 'us-east-1',
            'key' => 'arn:aws:kms:us-east-1:123456789:key/my-custom-key',
        ]),
    ]);

    $policy = $this->generator->generate($vaults, 'myapp');

    expect($policy['Statement'][1]['Resource'])->toBe('arn:aws:kms:us-east-1:123456789:key/my-custom-key');
});

it('generates Secrets Manager policy with namespace condition', function () {
    $vaults = new VaultConfigCollection([
        'sm' => VaultConfig::fromArray([
            'slug' => 'sm',
            'driver' => 'secretsmanager',
            'name' => 'Secrets Manager',
            'region' => 'us-east-1',
        ]),
    ]);

    $policy = $this->generator->generate($vaults, 'myapp');

    expect($policy['Statement'])->toHaveCount(4);

    $readWrite = $policy['Statement'][0];
    expect($readWrite['Sid'])->toBe('KeepSecretsManagerReadWrite');
    expect($readWrite['Resource'])->toBe('*');
    expect($readWrite['Condition']['StringEquals']['secretsmanager:ResourceTag/Namespace'])->toBe('myapp');

    $list = $policy['Statement'][1];
    expect($list['Sid'])->toBe('KeepSecretsManagerList');
    expect($list['Action'])->toContain('secretsmanager:ListSecrets', 'secretsmanager:BatchGetSecretValue');
    expect($list)->not->toHaveKey('Condition');

    $create = $policy['Statement'][2];
    expect($create['Sid'])->toBe('KeepSecretsManagerCreate');
    expect($create['Condition']['StringEquals']['aws:RequestTag/Namespace'])->toBe('myapp');
    expect($create['Condition']['ForAllValues:StringEquals']['aws:TagKeys'])
        ->toContain('ManagedBy', 'Namespace', 'Env', 'VaultSlug')
        ->not->toContain('Scope');
});

it('includes Scope tag key when vault has scope configured', function () {
    $vaults = new VaultConfigCollection([
        'sm' => VaultConfig::fromArray([
            'slug' => 'sm',
            'driver' => 'secretsmanager',
            'name' => 'Secrets Manager',
            'scope' => 'app2',
            'region' => 'us-east-1',
        ]),
    ]);

    $policy = $this->generator->generate($vaults, 'myapp');

    $create = $policy['Statement'][2];
    expect($create['Condition']['ForAllValues:StringEquals']['aws:TagKeys'])->toContain('Scope');
});

it('combines SSM and Secrets Manager vaults into one policy', function () {
    $vaults = new VaultConfigCollection([
        'ssm' => VaultConfig::fromArray([
            'slug' => 'ssm',
            'driver' => 'ssm',
            'name' => 'SSM',
            'region' => 'us-east-1',
        ]),
        'sm' => VaultConfig::fromArray([
            'slug' => 'sm',
            'driver' => 'secretsmanager',
            'name' => 'SM',
            'region' => 'us-west-2',
        ]),
    ]);

    $policy = $this->generator->generate($vaults, 'myapp');

    // 2 SSM statements + 4 SM statements
    expect($policy['Statement'])->toHaveCount(6);

    $sids = array_column($policy['Statement'], 'Sid');
    expect($sids)->toContain('KeepSsmAccess', 'KeepSsmKms', 'KeepSecretsManagerReadWrite', 'KeepSecretsManagerList');
});

it('combines multiple SSM vaults into single statements', function () {
    $vaults = new VaultConfigCollection([
        'ssm1' => VaultConfig::fromArray([
            'slug' => 'ssm1',
            'driver' => 'ssm',
            'name' => 'SSM 1',
            'region' => 'us-east-1',
        ]),
        'ssm2' => VaultConfig::fromArray([
            'slug' => 'ssm2',
            'driver' => 'ssm',
            'name' => 'SSM 2',
            'scope' => 'app2',
            'region' => 'eu-west-1',
        ]),
    ]);

    $policy = $this->generator->generate($vaults, 'myapp');

    // Still just 2 statements (combined)
    expect($policy['Statement'])->toHaveCount(2);

    $resources = $policy['Statement'][0]['Resource'];
    expect($resources)->toBeArray();
    expect($resources)->toContain(
        'arn:aws:ssm:us-east-1:*:parameter/myapp/*',
        'arn:aws:ssm:eu-west-1:*:parameter/myapp/app2/*'
    );
});

it('returns single resource as string not array', function () {
    $vaults = new VaultConfigCollection([
        'ssm' => VaultConfig::fromArray([
            'slug' => 'ssm',
            'driver' => 'ssm',
            'name' => 'SSM',
            'region' => 'us-east-1',
        ]),
    ]);

    $policy = $this->generator->generate($vaults, 'myapp');

    expect($policy['Statement'][0]['Resource'])->toBeString();
    expect($policy['Statement'][1]['Resource'])->toBeString();
});

it('scopes SSM resources to specific envs', function () {
    $vaults = new VaultConfigCollection([
        'ssm' => VaultConfig::fromArray([
            'slug' => 'ssm',
            'driver' => 'ssm',
            'name' => 'SSM',
            'region' => 'us-east-1',
        ]),
    ]);

    $policy = $this->generator->generate($vaults, 'myapp', ['local', 'staging']);

    $resources = $policy['Statement'][0]['Resource'];
    expect($resources)->toBeArray();
    expect($resources)->toContain(
        'arn:aws:ssm:us-east-1:*:parameter/myapp/local/*',
        'arn:aws:ssm:us-east-1:*:parameter/myapp/staging/*'
    );
    expect($resources)->not->toContain('arn:aws:ssm:us-east-1:*:parameter/myapp/*');
});

it('scopes SSM resources with scope and envs', function () {
    $vaults = new VaultConfigCollection([
        'ssm' => VaultConfig::fromArray([
            'slug' => 'ssm',
            'driver' => 'ssm',
            'name' => 'SSM',
            'scope' => 'app2',
            'region' => 'us-east-1',
        ]),
    ]);

    $policy = $this->generator->generate($vaults, 'myapp', ['production']);

    expect($policy['Statement'][0]['Resource'])->toBe('arn:aws:ssm:us-east-1:*:parameter/myapp/app2/production/*');
});

it('adds env condition to Secrets Manager when envs provided', function () {
    $vaults = new VaultConfigCollection([
        'sm' => VaultConfig::fromArray([
            'slug' => 'sm',
            'driver' => 'secretsmanager',
            'name' => 'SM',
            'region' => 'us-east-1',
        ]),
    ]);

    $policy = $this->generator->generate($vaults, 'myapp', ['local', 'staging']);

    $readWrite = $policy['Statement'][0];
    expect($readWrite['Condition']['ForAnyValue:StringEquals']['secretsmanager:ResourceTag/Env'])
        ->toBe(['local', 'staging']);

    $create = $policy['Statement'][2];
    expect($create['Condition']['ForAnyValue:StringEquals']['aws:RequestTag/Env'])
        ->toBe(['local', 'staging']);
});

it('omits env condition from Secrets Manager when no envs provided', function () {
    $vaults = new VaultConfigCollection([
        'sm' => VaultConfig::fromArray([
            'slug' => 'sm',
            'driver' => 'secretsmanager',
            'name' => 'SM',
            'region' => 'us-east-1',
        ]),
    ]);

    $policy = $this->generator->generate($vaults, 'myapp');

    $readWrite = $policy['Statement'][0];
    expect($readWrite['Condition'])->not->toHaveKey('ForAnyValue:StringEquals');

    $create = $policy['Statement'][2];
    expect($create['Condition'])->not->toHaveKey('ForAnyValue:StringEquals');
});

it('uses string instead of array for single env in Secrets Manager condition', function () {
    $vaults = new VaultConfigCollection([
        'sm' => VaultConfig::fromArray([
            'slug' => 'sm',
            'driver' => 'secretsmanager',
            'name' => 'SM',
            'region' => 'us-east-1',
        ]),
    ]);

    $policy = $this->generator->generate($vaults, 'myapp', ['production']);

    $readWrite = $policy['Statement'][0];
    expect($readWrite['Condition']['ForAnyValue:StringEquals']['secretsmanager:ResourceTag/Env'])
        ->toBe('production');
});

it('generates empty statements for unknown drivers', function () {
    $vaults = new VaultConfigCollection([
        'custom' => VaultConfig::fromArray([
            'slug' => 'custom',
            'driver' => 'custom',
            'name' => 'Custom',
        ]),
    ]);

    $policy = $this->generator->generate($vaults, 'myapp');

    expect($policy['Statement'])->toBeEmpty();
});

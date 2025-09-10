<?php

namespace STS\Keep\Tests\Unit\Server\Controllers;

use STS\Keep\Server\Controllers\VaultController;
use STS\Keep\KeepManager;
use STS\Keep\Data\Collections\VaultConfigCollection;
use STS\Keep\Data\VaultConfig;

test('list returns configured vaults', function () {
    $mockManager = $this->createPartialMock(KeepManager::class, ['getConfiguredVaults', 'getDefaultVault']);
    
    $vaultConfigs = new VaultConfigCollection([
        new VaultConfig('aws', 'aws-ssm', 'AWS Vault', '', []),
        new VaultConfig('local', 'local', 'Local Vault', '', [])
    ]);
    
    $mockManager->method('getConfiguredVaults')->willReturn($vaultConfigs);
    $mockManager->method('getDefaultVault')->willReturn('aws');
    
    $controller = new VaultController($mockManager);
    $response = $controller->list();
    
    expect($response)->toHaveKey('vaults');
    expect($response['vaults'])->toHaveCount(2);
    expect($response['vaults'][0]['slug'])->toBe('aws');
    expect($response['vaults'][0]['isDefault'])->toBeTrue();
    expect($response['vaults'][1]['isDefault'])->toBeFalse();
});

test('listEnvs returns configured envs', function () {
    $mockManager = $this->createPartialMock(KeepManager::class, ['getEnvs']);
    $mockManager->method('getEnvs')->willReturn(['local', 'staging', 'production', 'custom']);
    
    $controller = new VaultController($mockManager);
    $response = $controller->listEnvs();
    
    expect($response)->toHaveKey('envs');
    expect($response['envs'])->toHaveCount(4);
    expect($response['envs'])->toContain('custom');
});

test('getSettings returns app configuration', function () {
    $mockManager = $this->createPartialMock(KeepManager::class, ['getSettings', 'getDefaultVault', 'getEnvs']);
    $mockManager->method('getSettings')->willReturn([
        'app_name' => 'My App',
        'namespace' => 'MYAPP',
        'default_env' => 'local'
    ]);
    $mockManager->method('getEnvs')->willReturn(['local', 'prod']);
    $mockManager->method('getDefaultVault')->willReturn('aws');
    
    $controller = new VaultController($mockManager);
    $response = $controller->getSettings();
    
    expect($response)->toHaveKeys(['app_name', 'namespace', 'envs', 'default_vault', 'template_path', 'keep_version']);
    expect($response['app_name'])->toBe('My App');
    expect($response['namespace'])->toBe('MYAPP');
    expect($response['envs'])->toBe(['local', 'prod']);
    expect($response['default_vault'])->toBe('aws');
});

test('addEnv validates and adds new env', function () {
    $mockManager = $this->createPartialMock(KeepManager::class, ['getSettings']);
    $mockManager->method('getSettings')->willReturn([
        'envs' => ['local', 'staging', 'production'],
        'version' => '1.0'
    ]);
    
    // Test adding new env
    $controller = new VaultController($mockManager, [], ['env' => 'qa']);
    $response = $controller->addEnv();
    
    expect($response)->toHaveKey('envs');
    expect($response['envs'])->toContain('qa');
    expect($response['envs'])->toHaveCount(4);
    
    // Test duplicate env
    $controller = new VaultController($mockManager, [], ['env' => 'local']);
    $response = $controller->addEnv();
    
    expect($response)->toHaveKey('error');
    expect($response['error'])->toContain('already exists');
});

test('removeEnv prevents removing system envs', function () {
    $mockManager = $this->createPartialMock(KeepManager::class, ['getSettings']);
    $mockManager->method('getSettings')->willReturn([
        'envs' => ['local', 'staging', 'production', 'custom'],
        'version' => '1.0'
    ]);
    
    // Test removing custom env
    $controller = new VaultController($mockManager, [], ['env' => 'custom']);
    $response = $controller->removeEnv();
    
    expect($response)->toHaveKey('envs');
    expect($response['envs'])->not->toContain('custom');
    expect($response['envs'])->toHaveCount(3);
    
    // Test removing system env
    $controller = new VaultController($mockManager, [], ['env' => 'local']);
    $response = $controller->removeEnv();
    
    expect($response)->toHaveKey('error');
    expect($response['error'])->toContain('Cannot remove system environment');
});

test('diff returns comparison matrix', function () {
    $vault1 = new \STS\Keep\Tests\Support\TestVault('vault1', [], 'local');
    $vault1->set('SECRET1', 'value1');
    $vault1->set('SECRET2', 'value2');
    
    $vault2 = new \STS\Keep\Tests\Support\TestVault('vault1', [], 'prod');
    $vault2->set('SECRET1', 'value1_prod');
    
    $mockManager = $this->createPartialMock(KeepManager::class, ['vault', 'getConfiguredVaults']);
    $mockManager->method('vault')
        ->willReturnMap([
            ['vault1', 'local', $vault1],
            ['vault1', 'prod', $vault2]
        ]);
    
    $vaultConfigs = new VaultConfigCollection([
        new VaultConfig('vault1', 'test', 'Vault 1', '', [])
    ]);
    $mockManager->method('getConfiguredVaults')->willReturn($vaultConfigs);
    
    $controller = new VaultController($mockManager, ['envs' => 'local,prod', 'vaults' => 'vault1']);
    $response = $controller->diff();
    
    expect($response)->toHaveKey('diff');
    expect($response['diff'])->toHaveKey('SECRET1');
    expect($response['diff']['SECRET1']['vault1']['local'])->toBe('value1');
    expect($response['diff']['SECRET1']['vault1']['prod'])->toBe('value1_prod');
    expect($response['diff'])->toHaveKey('SECRET2');
    expect($response['diff']['SECRET2']['vault1'])->toHaveKey('local');
    expect($response['diff']['SECRET2']['vault1'])->not->toHaveKey('prod');
});
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

test('listStages returns configured stages', function () {
    $mockManager = $this->createPartialMock(KeepManager::class, ['getStages']);
    $mockManager->method('getStages')->willReturn(['local', 'staging', 'production', 'custom']);
    
    $controller = new VaultController($mockManager);
    $response = $controller->listStages();
    
    expect($response)->toHaveKey('stages');
    expect($response['stages'])->toHaveCount(4);
    expect($response['stages'])->toContain('custom');
});

test('getSettings returns app configuration', function () {
    $mockManager = $this->createPartialMock(KeepManager::class, ['getSettings', 'getDefaultVault', 'getStages']);
    $mockManager->method('getSettings')->willReturn([
        'app_name' => 'My App',
        'namespace' => 'MYAPP',
        'default_stage' => 'local'
    ]);
    $mockManager->method('getStages')->willReturn(['local', 'prod']);
    $mockManager->method('getDefaultVault')->willReturn('aws');
    
    $controller = new VaultController($mockManager);
    $response = $controller->getSettings();
    
    expect($response)->toHaveKeys(['app_name', 'namespace', 'stages', 'default_vault', 'template_path', 'keep_version']);
    expect($response['app_name'])->toBe('My App');
    expect($response['namespace'])->toBe('MYAPP');
    expect($response['stages'])->toBe(['local', 'prod']);
    expect($response['default_vault'])->toBe('aws');
});

test('addStage validates and adds new stage', function () {
    $mockManager = $this->createPartialMock(KeepManager::class, ['getSettings']);
    $mockManager->method('getSettings')->willReturn([
        'stages' => ['local', 'staging', 'production'],
        'version' => '1.0'
    ]);
    
    // Test adding new stage
    $controller = new VaultController($mockManager, [], ['stage' => 'qa']);
    $response = $controller->addStage();
    
    expect($response)->toHaveKey('stages');
    expect($response['stages'])->toContain('qa');
    expect($response['stages'])->toHaveCount(4);
    
    // Test duplicate stage
    $controller = new VaultController($mockManager, [], ['stage' => 'local']);
    $response = $controller->addStage();
    
    expect($response)->toHaveKey('error');
    expect($response['error'])->toContain('already exists');
});

test('removeStage prevents removing system stages', function () {
    $mockManager = $this->createPartialMock(KeepManager::class, ['getSettings']);
    $mockManager->method('getSettings')->willReturn([
        'stages' => ['local', 'staging', 'production', 'custom'],
        'version' => '1.0'
    ]);
    
    // Test removing custom stage
    $controller = new VaultController($mockManager, [], ['stage' => 'custom']);
    $response = $controller->removeStage();
    
    expect($response)->toHaveKey('stages');
    expect($response['stages'])->not->toContain('custom');
    expect($response['stages'])->toHaveCount(3);
    
    // Test removing system stage
    $controller = new VaultController($mockManager, [], ['stage' => 'local']);
    $response = $controller->removeStage();
    
    expect($response)->toHaveKey('error');
    expect($response['error'])->toContain('Cannot remove system stage');
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
    
    $controller = new VaultController($mockManager, ['stages' => 'local,prod', 'vaults' => 'vault1']);
    $response = $controller->diff();
    
    expect($response)->toHaveKey('diff');
    expect($response['diff'])->toHaveKey('SECRET1');
    expect($response['diff']['SECRET1']['vault1']['local'])->toBe('value1');
    expect($response['diff']['SECRET1']['vault1']['prod'])->toBe('value1_prod');
    expect($response['diff'])->toHaveKey('SECRET2');
    expect($response['diff']['SECRET2']['vault1'])->toHaveKey('local');
    expect($response['diff']['SECRET2']['vault1'])->not->toHaveKey('prod');
});
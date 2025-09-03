<?php

namespace STS\Keep\Tests\Unit\Server\Controllers;

use STS\Keep\Data\Secret;
use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Server\Controllers\SecretController;
use STS\Keep\KeepManager;
use STS\Keep\Data\Settings;
use STS\Keep\Data\Collections\VaultConfigCollection;
use STS\Keep\Vaults\TestVault;

test('list returns all secrets for vault and stage', function () {
    $vault = new TestVault('test', 'local');
    $vault->set('API_KEY', 'secret123');
    $vault->set('DB_PASSWORD', 'pass456');
    
    // Mock the manager to return our test vault
    $settings = new Settings([]);
    $vaultConfigs = new VaultConfigCollection();
    $mockManager = $this->createPartialMock(KeepManager::class, ['vault']);
    $mockManager->method('vault')->willReturn($vault);
    
    $controller = new SecretController($mockManager, ['vault' => 'test', 'stage' => 'local']);
    $response = $controller->list();
    
    expect($response)->toHaveKey('secrets');
    expect($response['secrets'])->toHaveCount(2);
});

test('get returns single secret with unmask option', function () {
    $vault = new TestVault('test', 'local');
    $vault->set('API_KEY', 'secret123');
    
    $mockManager = $this->createPartialMock(KeepManager::class, ['vault']);
    $mockManager->method('vault')->willReturn($vault);
    
    // Test masked
    $controller = new SecretController($mockManager, ['vault' => 'test', 'stage' => 'local']);
    $response = $controller->get('API_KEY');
    
    expect($response)->toHaveKey('secret');
    expect($response['secret']['key'])->toBe('API_KEY');
    expect($response['secret']['masked'])->toBeTrue();
    
    // Test unmasked
    $controller = new SecretController($mockManager, ['vault' => 'test', 'stage' => 'local', 'unmask' => 'true']);
    $response = $controller->get('API_KEY');
    
    expect($response['secret']['value'])->toBe('secret123');
    expect($response['secret']['masked'])->toBeFalse();
});

test('create adds new secret to vault', function () {
    $vault = new TestVault('test', 'local');
    
    $mockManager = $this->createPartialMock(KeepManager::class, ['vault']);
    $mockManager->method('vault')->willReturn($vault);
    
    $controller = new SecretController(
        $mockManager, 
        ['vault' => 'test', 'stage' => 'local'],
        ['key' => 'NEW_SECRET', 'value' => 'new_value']
    );
    $response = $controller->create();
    
    expect($response)->toHaveKey('success');
    expect($response['success'])->toBeTrue();
    expect($vault->get('NEW_SECRET')->value())->toBe('new_value');
});

test('delete removes secret from vault', function () {
    $vault = new TestVault('test', 'local');
    $vault->set('TO_DELETE', 'value');
    
    $mockManager = $this->createPartialMock(KeepManager::class, ['vault']);
    $mockManager->method('vault')->willReturn($vault);
    
    $controller = new SecretController($mockManager, ['vault' => 'test', 'stage' => 'local']);
    
    expect($vault->get('TO_DELETE'))->not->toBeNull();
    
    $response = $controller->delete('TO_DELETE');
    
    expect($response)->toHaveKey('success');
    expect($response['success'])->toBeTrue();
    expect($vault->get('TO_DELETE'))->toBeNull();
});

test('search filters secrets by query', function () {
    $vault = new TestVault('test', 'local');
    $vault->set('API_KEY', 'contains_api');
    $vault->set('DB_PASSWORD', 'database123');
    $vault->set('SECRET_TOKEN', 'token_api_456');
    
    $mockManager = $this->createPartialMock(KeepManager::class, ['vault']);
    $mockManager->method('vault')->willReturn($vault);
    
    $controller = new SecretController($mockManager, ['vault' => 'test', 'stage' => 'local', 'q' => 'api']);
    $response = $controller->search();
    
    expect($response)->toHaveKey('secrets');
    expect($response['secrets'])->toHaveCount(2); // API_KEY and SECRET_TOKEN
});
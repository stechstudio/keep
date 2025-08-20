<?php

it('shows system information', function () {
    $tempDir = createTempKeepDir();
    
    // Create .keep directory and settings to initialize Keep
    mkdir('.keep');
    mkdir('.keep/vaults');
    
    $settings = [
        'app_name' => 'test-app',
        'namespace' => 'test-app',
        'default_vault' => null,
        'stages' => ['development', 'production'],
        'created_at' => date('c'),
        'version' => '1.0'
    ];
    
    file_put_contents('.keep/settings.json', json_encode($settings, JSON_PRETTY_PRINT));
    
    $commandTester = runCommand('info');
    
    expect($commandTester->getStatusCode())->toBe(0);
    
    $output = stripAnsi($commandTester->getDisplay());
    
    expect($output)->toContain('Keep Secret Management Tool');
    expect($output)->toContain('Version');
    expect($output)->toContain('Working Directory');
    expect($output)->toContain('PHP Version');
    expect($output)->toContain('Binary Path');
    
    cleanupTempDir($tempDir);
});

it('shows uninitialized message when Keep is not configured', function () {
    $tempDir = createTempKeepDir();
    
    $commandTester = runCommand('info');
    
    expect($commandTester->getStatusCode())->toBe(1);
    
    $output = stripAnsi($commandTester->getDisplay());
    expect($output)->toContain('Keep is not initialized in this directory');
    expect($output)->toContain('Run: keep configure');
    
    cleanupTempDir($tempDir);
});

it('shows configuration when Keep is initialized', function () {
    $tempDir = createTempKeepDir();
    
    // Create .keep directory and settings
    mkdir('.keep');
    mkdir('.keep/vaults');
    
    $settings = [
        'app_name' => 'test-app',
        'namespace' => 'test-app',
        'default_vault' => null,
        'stages' => ['development', 'production'],
        'created_at' => date('c'),
        'version' => '1.0'
    ];
    
    file_put_contents('.keep/settings.json', json_encode($settings, JSON_PRETTY_PRINT));
    
    $commandTester = runCommand('info');
    
    expect($commandTester->getStatusCode())->toBe(0);
    
    $output = stripAnsi($commandTester->getDisplay());
    expect($output)->toContain('Configuration');
    expect($output)->toContain('test-app');
    expect($output)->toContain('development, production');
    expect($output)->toContain('No vaults configured');
    
    cleanupTempDir($tempDir);
});

it('shows configured vaults when they exist', function () {
    $tempDir = createTempKeepDir();
    
    // Create .keep directory and settings
    mkdir('.keep');
    mkdir('.keep/vaults');
    
    $settings = [
        'app_name' => 'test-app',
        'namespace' => 'test-app',
        'default_vault' => 'test-vault',
        'stages' => ['development', 'production'],
        'created_at' => date('c'),
        'version' => '1.0'
    ];
    
    file_put_contents('.keep/settings.json', json_encode($settings, JSON_PRETTY_PRINT));
    
    // Create a test vault
    $vaultConfig = [
        'driver' => 'ssm',
        'name' => 'Test Vault',
        'region' => 'us-east-1'
    ];
    
    file_put_contents('.keep/vaults/test-vault.json', json_encode($vaultConfig, JSON_PRETTY_PRINT));
    
    $commandTester = runCommand('info');
    
    expect($commandTester->getStatusCode())->toBe(0);
    
    $output = stripAnsi($commandTester->getDisplay());
    expect($output)->toContain('Configured Vaults');
    expect($output)->toContain('test-vault (default)');
    expect($output)->toContain('Test Vault');
    expect($output)->toContain('ssm');
    
    cleanupTempDir($tempDir);
});


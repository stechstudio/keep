<?php

it('shows no vaults message when none are configured', function () {
    $tempDir = createTempKeepDir();

    // Create basic Keep configuration without vaults
    mkdir('.keep');
    mkdir('.keep/vaults');

    $settings = [
        'app_name' => 'test-app',
        'namespace' => 'test-app',
        'default_vault' => null,
        'stages' => ['development'],
        'created_at' => date('c'),
        'version' => '1.0',
    ];

    file_put_contents('.keep/settings.json', json_encode($settings, JSON_PRETTY_PRINT));

    $commandTester = runCommand('vault:list');

    expect($commandTester->getStatusCode())->toBe(0);

    $output = stripAnsi($commandTester->getDisplay());
    expect($output)->toContain('No vaults are configured yet');
    expect($output)->toContain('Add your first vault with: keep vault:add');

    cleanupTempDir($tempDir);
});

it('lists configured vaults', function () {
    $tempDir = createTempKeepDir();

    // Create Keep configuration with vaults
    mkdir('.keep');
    mkdir('.keep/vaults');

    $settings = [
        'app_name' => 'test-app',
        'namespace' => 'test-app',
        'default_vault' => 'primary',
        'stages' => ['development'],
        'created_at' => date('c'),
        'version' => '1.0',
    ];

    file_put_contents('.keep/settings.json', json_encode($settings, JSON_PRETTY_PRINT));

    // Create test vaults
    $primaryVault = [
        'driver' => 'test',
        'name' => 'Primary Vault',
        'region' => 'us-east-1',
    ];

    $secondaryVault = [
        'driver' => 'test',
        'name' => 'Secondary Vault',
        'region' => 'us-west-2',
    ];

    file_put_contents('.keep/vaults/primary.json', json_encode($primaryVault, JSON_PRETTY_PRINT));
    file_put_contents('.keep/vaults/secondary.json', json_encode($secondaryVault, JSON_PRETTY_PRINT));

    $commandTester = runCommand('vault:list');

    expect($commandTester->getStatusCode())->toBe(0);

    $output = stripAnsi($commandTester->getDisplay());
    expect($output)->toContain('Configured Vaults');
    expect($output)->toContain('primary');
    expect($output)->toContain('Primary Vault');
    expect($output)->toContain('test');
    expect($output)->toContain('secondary');
    expect($output)->toContain('Secondary Vault');
    expect($output)->toContain('test');
    expect($output)->toContain('✓'); // Default vault indicator

    cleanupTempDir($tempDir);
});

it('requires Keep to be initialized', function () {
    $tempDir = createTempKeepDir();

    $commandTester = runCommand('vault:list');

    expect($commandTester->getStatusCode())->toBe(1); // Should fail

    $output = stripAnsi($commandTester->getDisplay());
    expect($output)->toContain('Keep is not initialized in this directory');
    expect($output)->toContain('Run: keep configure');

    cleanupTempDir($tempDir);
});

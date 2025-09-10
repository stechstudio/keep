<?php

use STS\Keep\Facades\Keep;
use STS\Keep\Tests\Support\TestVault;

beforeEach(function() {
    // Create temp directory for templates
    $this->tempDir = sys_get_temp_dir() . '/keep_template_test_' . uniqid();
    mkdir($this->tempDir, 0755, true);
    
    // Change to temp directory to avoid polluting real project
    $this->originalDir = getcwd();
    $tempWorkDir = sys_get_temp_dir() . '/keep_test_' . uniqid();
    mkdir($tempWorkDir, 0755, true);
    mkdir($tempWorkDir . '/.keep', 0755, true);
    mkdir($tempWorkDir . '/.keep/vaults', 0755, true);
    chdir($tempWorkDir);
    
    // Create settings.json
    $settings = [
        'app_name' => 'test-app',
        'namespace' => 'test-app',
        'default_vault' => 'test-vault',
        'envs' => ['local', 'development', 'production'],
        'created_at' => date('c'),
        'updated_at' => date('c'),
        'version' => '1.0',
    ];
    file_put_contents('.keep/settings.json', json_encode($settings, JSON_PRETTY_PRINT));
    
    // Clear any existing vaults
    TestVault::clearAll();
    
    // Set up KeepManager with vault configurations
    setupKeepManager([
        'app_name' => 'test-app',
        'namespace' => 'test-app',
        'default_vault' => 'test-vault',
        'envs' => ['local', 'development', 'production'],
    ], [
        'test-vault' => [
            'slug' => 'test-vault',
            'driver' => 'test',
            'name' => 'Test Vault',
            'scope' => '',
        ],
        'second-vault' => [
            'slug' => 'second-vault',
            'driver' => 'test',
            'name' => 'Second Vault',
            'scope' => '',
        ],
    ]);
    
    // Also create the vault configuration files for the commands to find
    $testVaultConfig = [
        'slug' => 'test-vault',
        'driver' => 'test',
        'name' => 'Test Vault',
        'scope' => '',
    ];
    file_put_contents('.keep/vaults/test-vault.json', json_encode($testVaultConfig, JSON_PRETTY_PRINT));
    
    $secondVaultConfig = [
        'slug' => 'second-vault',
        'driver' => 'test',
        'name' => 'Second Vault',
        'scope' => '',
    ];
    file_put_contents('.keep/vaults/second-vault.json', json_encode($secondVaultConfig, JSON_PRETTY_PRINT));
    
    // Register test vault driver
    Keep::addVaultDriver(TestVault::class);
    
    // Add some test secrets
    Keep::vault('test-vault', 'local')->set('db-password', 'secret123');
    Keep::vault('test-vault', 'local')->set('api-key', 'key456');
    Keep::vault('test-vault', 'local')->set('APP_URL', 'https://example.com');
    
    Keep::vault('second-vault', 'local')->set('mail-username', 'user@example.com');
    Keep::vault('second-vault', 'local')->set('mail-password', 'mailpass');
});

afterEach(function() {
    // Clean up temp directory
    if (is_dir($this->tempDir)) {
        deleteDirectory($this->tempDir);
    }
    
    // Restore original directory
    chdir($this->originalDir);
    
    // Clear test vaults
    TestVault::clearAll();
});

// Helper function to recursively delete directory
function deleteDirectory($dir) {
    if (!is_dir($dir)) return;
    
    $objects = scandir($dir);
    foreach ($objects as $object) {
        if ($object != "." && $object != "..") {
            if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object)) {
                deleteDirectory($dir . DIRECTORY_SEPARATOR . $object);
            } else {
                unlink($dir . DIRECTORY_SEPARATOR . $object);
            }
        }
    }
    rmdir($dir);
}

test('template:add creates template for env', function() {
    $commandTester = runCommand('template:add', [
        '--env' => 'local',
        '--path' => $this->tempDir,
        '--no-interaction' => true,
    ]);
    
    // Check command succeeded
    expect($commandTester->getStatusCode())->toBe(0);
    
    // Verify template was created
    expect(file_exists($this->tempDir . '/local.env'))->toBeTrue();
    
    // Verify template content
    $content = file_get_contents($this->tempDir . '/local.env');
    
    // Check for header
    expect($content)->toContain('# Keep Template - Environment: local');
    
    // Check for test-vault secrets
    expect($content)->toContain('# ===== Vault: test-vault =====');
    expect($content)->toContain('DB_PASSWORD={test-vault:db-password}');
    expect($content)->toContain('API_KEY={test-vault:api-key}');
    expect($content)->toContain('APP_URL={test-vault:APP_URL}');
    
    // Check for second-vault secrets
    expect($content)->toContain('# ===== Vault: second-vault =====');
    expect($content)->toContain('MAIL_USERNAME={second-vault:mail-username}');
    expect($content)->toContain('MAIL_PASSWORD={second-vault:mail-password}');
    
    // Check for non-secret section
    expect($content)->toContain('# ===== Application Settings (non-secret) =====');
    expect($content)->toContain('# APP_NAME=MyApp');
});

test('template:add fails if template already exists', function() {
    // Create existing template
    file_put_contents($this->tempDir . '/local.env', 'EXISTING=template');
    
    $commandTester = runCommand('template:add', [
        '--env' => 'local',
        '--path' => $this->tempDir,
        '--no-interaction' => true,
    ]);
    
    // Check command failed
    expect($commandTester->getStatusCode())->toBe(1);
    
    // Check error message
    expect($commandTester->getDisplay())->toContain("Template already exists for environment 'local': local.env");
});

test('template:add with no secrets', function() {
    // Remove all secrets
    Keep::vault('test-vault', 'local')->delete('db-password');
    Keep::vault('test-vault', 'local')->delete('api-key');
    Keep::vault('test-vault', 'local')->delete('APP_URL');
    Keep::vault('second-vault', 'local')->delete('mail-username');
    Keep::vault('second-vault', 'local')->delete('mail-password');
    
    $commandTester = runCommand('template:add', [
        '--env' => 'local',
        '--path' => $this->tempDir,
        '--no-interaction' => true,
    ]);
    
    // Check command failed
    expect($commandTester->getStatusCode())->toBe(1);
    
    // Check error message
    expect($commandTester->getDisplay())->toContain("No secrets found for environment 'local'");
});

test('template:add shows preview and next steps', function() {
    $commandTester = runCommand('template:add', [
        '--env' => 'local',
        '--path' => $this->tempDir,
        '--no-interaction' => true,
    ]);
    
    $output = $commandTester->getDisplay();
    
    // Check for preview
    expect($output)->toContain('Template preview:');
    expect($output)->toContain('# Keep Template - Environment: local');
    expect($output)->toContain('DB_PASSWORD={test-vault:db-password}');
    
    // Check for next steps
    expect($output)->toContain('Next steps:');
    expect($output)->toContain('Review and customize the generated template');
    expect($output)->toContain('Test with: keep template:validate local.env --env=local');
    expect($output)->toContain('Export with: keep export --template=local.env --env=local');
});
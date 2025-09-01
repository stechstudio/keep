<?php

use STS\Keep\Enums\KeepInstall;
use STS\Keep\Facades\Keep;
use STS\Keep\KeepApplication;
use STS\Keep\Tests\Support\TestVault;
use Symfony\Component\Console\Tester\CommandTester;

uses()->in('Feature');
uses()->in('Unit');

// Set environment variable to disable Laravel Prompts interactivity during tests
putenv('LARAVEL_PROMPTS_INTERACT=0');

/**
 * Helper to create a KeepApplication instance for testing
 */
function createKeepApp(): KeepApplication
{
    return new KeepApplication(KeepInstall::LOCAL);
}

/**
 * Helper to run a command and return the CommandTester
 */
function runCommand(string $commandName, array $input = []): CommandTester
{
    $app = createKeepApp();

    // Register TestVault driver for testing to avoid hitting real AWS services
    Keep::addVaultDriver(TestVault::class);

    // Note: Tests should manage their own TestVault cleanup in beforeEach

    $command = $app->find($commandName);
    $commandTester = new CommandTester($command);

    // Set non-interactive mode to prevent prompts during testing
    $input['--no-interaction'] = true;

    $commandTester->execute($input);

    return $commandTester;
}

/**
 * Helper to create a temporary directory for testing
 */
function createTempKeepDir(): string
{
    $tempDir = sys_get_temp_dir().'/keep-test-'.uniqid();
    mkdir($tempDir, 0755, true);

    // Change to temp directory for tests
    chdir($tempDir);

    return $tempDir;
}

/**
 * Helper to clean up temp directory
 */
function cleanupTempDir(string $tempDir): void
{
    if (is_dir($tempDir)) {
        exec('rm -rf '.escapeshellarg($tempDir));
    }
}

/**
 * Helper to strip ANSI codes from command output
 */
function stripAnsi(string $text): string
{
    return preg_replace('/\x1b\[[0-9;]*m/', '', $text);
}

/**
 * Helper to set up KeepManager for unit tests
 */
function setupKeepManager(array $settingsOverride = [], array $vaults = []): \STS\Keep\KeepManager
{
    // Default test settings
    $defaultSettings = [
        'app_name' => 'test-app',
        'namespace' => 'test-app',
        'default_vault' => 'test',
        'stages' => ['testing', 'production'],
    ];

    // Default test vault
    $defaultVaults = [
        'test' => [
            'slug' => 'test',
            'driver' => 'test',
            'name' => 'Test Vault',
            'namespace' => 'test-app',
        ],
    ];

    $settingsData = array_merge($defaultSettings, $settingsOverride);
    $vaultsData = array_merge($defaultVaults, $vaults);

    // Create Settings object instead of using array
    $settings = \STS\Keep\Data\Settings::fromArray($settingsData);

    // Convert vault arrays to VaultConfig objects and create VaultConfigCollection
    $vaultConfigs = [];
    foreach ($vaultsData as $name => $config) {
        // Ensure slug is set if not provided
        if (! isset($config['slug'])) {
            $config['slug'] = $name;
        }
        $vaultConfigs[$name] = \STS\Keep\Data\VaultConfig::fromArray($config);
    }
    $vaultsCollection = new \STS\Keep\Data\Collections\VaultConfigCollection($vaultConfigs);

    // Create KeepManager with Settings object and VaultConfigCollection
    $manager = new \STS\Keep\KeepManager($settings, $vaultsCollection);

    // Bind to container first
    \STS\Keep\KeepContainer::getInstance()->singleton(\STS\Keep\KeepManager::class, fn () => $manager);

    // Register TestVault driver and clear storage for isolation
    Keep::addVaultDriver(TestVault::class);
    TestVault::clearAll();

    return $manager;
}

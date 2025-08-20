<?php

namespace STS\Keep\Commands;

use Illuminate\Support\Str;
use function Laravel\Prompts\text;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

class ConfigureCommand extends BaseCommand
{
    protected $signature = 'configure';
    protected $description = 'Configure Keep settings for your project';
    
    protected function requiresInitialization(): bool
    {
        return false; // configure command should work whether initialized or not
    }
    
    protected function process(): int
    {
        // Welcome message
        info('🔐  Keep Configuration');
        note('Configure Keep settings for your project. Run this anytime to review or update your settings.');
        
        $existingSettings = $this->manager->getSettings();
        
        // Gather basic configuration
        $appName = text(
            label: 'What is your application name?',
            placeholder: 'My Awesome App',
            default: $existingSettings['app_name'] ?? $this->detectAppName()
        );

        $namespace = text(
            label: 'All secrets will be prefixed with this namespace. Would you like to change it?',
            default: $existingSettings['namespace'] ?? Str::slug($appName)
        );
        
        $stages = multiselect(
            label: 'Which environments/stages do you want to manage secrets for?',
            options: [
                'development' => 'Development (local dev)',
                'qa' => 'QA (test team validation)',
                'uat' => 'UAT (stakeholder testing)',
                'staging' => 'Staging (pre-production)',
                'sandbox' => 'Sandbox (demos / experiments)',
                'production' => 'Production (live)'
            ],
            default: $existingSettings['stages'] ?? ['development', 'staging', 'production'],
            scroll: 6,
            hint: 'You can add more later. Toggle with space bar, confirm with enter.',
        );
        
        // Create configuration structure
        $this->createKeepDirectory();
        $this->createGlobalSettings($appName, $namespace, $stages, $existingSettings);
        
        info('✅ Configuration updated successfully!');
        
        // Show next steps
        if (empty($this->manager->getConfiguredVaults())) {
            note('Next steps:');
            note('• Add your first vault: keep vault:add');
            note('• Set your first secret: keep set MY_SECRET');
        } else {
            note('Your configuration has been updated.');
        }
        
        return self::SUCCESS;
    }
    
    private function detectAppName(): string
    {
        $cwd = getcwd();
        
        // Try to detect from composer.json
        if (file_exists($cwd . '/composer.json')) {
            $composer = json_decode(file_get_contents($cwd . '/composer.json'), true);
            if (isset($composer['name'])) {
                $parts = explode('/', $composer['name']);
                return end($parts);
            }
        }
        
        // Try to detect from package.json
        if (file_exists($cwd . '/package.json')) {
            $package = json_decode(file_get_contents($cwd . '/package.json'), true);
            if (isset($package['name'])) {
                return $package['name'];
            }
        }
        
        // Fall back to directory name
        return basename($cwd);
    }
    
    private function createKeepDirectory(): void
    {
        $keepDir = getcwd() . '/.keep';
        
        if (!is_dir($keepDir)) {
            mkdir($keepDir, 0755, true);
        }
        
        if (!is_dir($keepDir . '/vaults')) {
            mkdir($keepDir . '/vaults', 0755, true);
        }
    }
    
    private function createGlobalSettings(string $appName, string $namespace, array $stages, array $existingSettings): void
    {
        $config = [
            'app_name' => $appName,
            'namespace' => $namespace,
            'default_vault' => $existingSettings['default_vault'] ?? null,
            'stages' => $stages,
            'created_at' => $existingSettings['created_at'] ?? date('c'),
            'updated_at' => date('c'),
            'version' => '1.0'
        ];
        
        $configPath = getcwd() . '/.keep/settings.json';
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
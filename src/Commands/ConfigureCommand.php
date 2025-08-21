<?php

namespace STS\Keep\Commands;

use Illuminate\Support\Str;
use STS\Keep\Commands\Concerns\ConfiguresVaults;
use STS\Keep\Facades\Keep;
use function Laravel\Prompts\text;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\confirm;

class ConfigureCommand extends BaseCommand
{
    use ConfiguresVaults;
    
    protected $signature = 'configure';
    protected $description = 'Configure Keep settings for your project';
    
    protected function requiresInitialization(): bool
    {
        return false; // configure command should work whether initialized or not
    }
    
    protected function process()
    {
        // Welcome message
        info('🔐  Keep Configuration');
        note('Configure Keep settings for your project. Run this anytime to review or update your settings.');
        
        $existingSettings = Keep::getSettings();
        
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
                'local' => 'Local (development)',
                'qa' => 'QA (test team validation)',
                'uat' => 'UAT (stakeholder testing)',
                'staging' => 'Staging (pre-production)',
                'sandbox' => 'Sandbox (demos / experiments)',
                'production' => 'Production (live)'
            ],
            default: $existingSettings['stages'] ?? ['local', 'staging', 'production'],
            scroll: 6,
            hint: 'You can add more later. Toggle with space bar, confirm with enter.',
        );
        
        // Create configuration structure
        $this->createKeepDirectory();
        $this->createGlobalSettings($appName, $namespace, $stages, $existingSettings);
        
        info('✅ Configuration updated successfully!');
        
        // Offer to create first vault if none exist (but not in non-interactive mode)
        if (Keep::getConfiguredVaults()->isEmpty() && !$this->option('no-interaction')) {
            info('🗄️  Vault Setup');
            note('You\'ll need at least one vault to store your secrets.');
            
            if (confirm('Would you like to set up your first vault now?', true)) {
                $result = $this->configureNewVault();
                
                if ($result) {
                    note('🎉 All set! Your Keep configuration is ready to use.');
                    note('Next step: Set your first secret with: keep set MY_SECRET');
                } else {
                    note('No worries! You can add a vault later with: keep vault:add');
                }
            } else {
                note('No worries! You can add a vault later with: keep vault:add');
            }
        } else {
            if (Keep::getConfiguredVaults()->isEmpty()) {
                note('Next steps:');
                note('• Add your first vault: keep vault:add');
                note('• Set your first secret: keep set MY_SECRET');
            }
        }
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
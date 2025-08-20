<?php

namespace STS\Keep\Commands;

use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\error;

class InitCommand extends BaseCommand
{
    protected function requiresInitialization(): bool
    {
        return false; // init command should run even when not initialized
    }
    
    protected function configure(): void
    {
        $this->setName('init')
             ->setDescription('Initialize Keep in your project')
             ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing configuration');
    }
    
    protected function handle(InputInterface $input, OutputInterface $output)
    {
        // Welcome message
        info('🔐  Welcome to Keep!');
        note('Keep is your toolkit for collaborative, secure management of secrets across applications, environments, and teams.');
        
        // Check if already initialized
        if ($this->manager->isInitialized() && !$input->getOption('force')) {
            error('Keep is already initialized in this directory!');
            $override = confirm('Do you want to reinitialize?', false);
            if (!$override) {
                info('Initialization cancelled.');
                return false;
            }
        }
        
        info('Let\'s get you set up...');
        
        // Gather basic configuration
        $appName = text(
            label: 'What is your application name?',
            placeholder: 'My Awesome App',
            default: $this->detectAppName()
        );

        $namespace = text(
            label: 'All secrets will be prefixed with this namespace. Would you like to change it?',
            default: Str::slug($appName)
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
            default: ['development', 'staging', 'production'],
            scroll: 6,
            hint: 'You can add more later. Toggle with space bar, confirm with enter.',
        );
        
        // Create configuration structure
        $this->createKeepDirectory();
        $this->createGlobalSettings($appName, $namespace, $stages);
        
        info('✅ Created .keep directory and global settings');
        
        // Ask about first vault
        $setupVault = confirm(
            label: 'Would you like to configure your first vault now?',
            default: true
        );
        
        if ($setupVault) {
            $this->configureFirstVault();
        } else {
            note('You can configure vaults later with: keep configure');
        }
        
        // Final success message
        info('🎉 Keep has been initialized successfully!');
        note('Next steps:');
        note('• Configure your first vault: keep configure');
        note('• Set your first secret: keep set MY_SECRET');
        note('• List all secrets: keep list');
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
    
    private function createGlobalSettings(string $appName, string $namespace, array $stages): void
    {
        $config = [
            'app_name' => $appName,
            'namespace' => $namespace,
            'default_vault' => null, // Will be set when first vault is configured
            'stages' => $stages,
            'created_at' => date('c'),
            'version' => '1.0'
        ];
        
        $configPath = getcwd() . '/.keep/settings.json';
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    
    private function configureFirstVault(): void
    {
        info('Let\'s configure your first vault...');
        
        $vaultName = text(
            label: 'What should we call this vault?',
            default: 'default',
            hint: 'A friendly name to identify this vault'
        );
        
        $driver = select(
            label: 'Which vault driver would you like to use?',
            options: [
                'aws_ssm' => 'AWS Systems Manager Parameter Store',
                'aws_secrets_manager' => 'AWS Secrets Manager',
                // 'hashicorp' => 'HashiCorp Vault (coming soon)',
                // 'local' => 'Local file storage (development only)'
            ],
            default: 'aws_ssm'
        );
        
        match($driver) {
            'aws_ssm' => $this->configureAwsSsm($vaultName),
            'aws_secrets_manager' => $this->configureAwsSecretsManager($vaultName),
            default => error('Driver not yet implemented')
        };
    }
    
    private function configureAwsSsm(string $vaultName): void
    {
        info('Configuring AWS Systems Manager Parameter Store...');
        
        $region = text(
            label: 'AWS Region',
            default: 'us-east-1',
            hint: 'The AWS region where your parameters will be stored'
        );
        
        $prefix = text(
            label: 'Parameter prefix',
            default: '/app-secrets',
            hint: 'Base path for all your parameters (e.g., /app-secrets/myapp/production/DB_PASSWORD)'
        );
        
        $kmsKey = text(
            label: 'KMS Key ID (optional)',
            hint: 'Leave empty to use the default AWS managed key'
        );
        
        $vaultConfig = [
            'driver' => 'aws_ssm',
            'region' => $region,
            'prefix' => $prefix
        ];
        
        if (!empty($kmsKey)) {
            $vaultConfig['kms_key'] = $kmsKey;
        }
        
        $this->saveVaultConfig($vaultName, $vaultConfig);
        $this->setDefaultVault($vaultName);
        
        info("✅ AWS SSM vault '{$vaultName}' configured successfully");
        note("Parameters will be stored at: {$prefix}/[app-name]/[stage]/[key]");
    }
    
    private function configureAwsSecretsManager(string $vaultName): void
    {
        info('Configuring AWS Secrets Manager...');
        
        $region = text(
            label: 'AWS Region',
            default: 'us-east-1'
        );
        
        $prefix = text(
            label: 'Secret name prefix',
            default: 'app-secrets',
            hint: 'Prefix for all your secret names'
        );
        
        $kmsKey = text(
            label: 'KMS Key ID (optional)',
            hint: 'Leave empty to use the default AWS managed key'
        );
        
        $vaultConfig = [
            'driver' => 'aws_secrets_manager',
            'region' => $region,
            'prefix' => $prefix
        ];
        
        if (!empty($kmsKey)) {
            $vaultConfig['kms_key'] = $kmsKey;
        }
        
        $this->saveVaultConfig($vaultName, $vaultConfig);
        $this->setDefaultVault($vaultName);
        
        info("✅ AWS Secrets Manager vault '{$vaultName}' configured successfully");
    }
    
    private function saveVaultConfig(string $name, array $config): void
    {
        $vaultPath = getcwd() . "/.keep/vaults/{$name}.json";
        file_put_contents($vaultPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    
    private function setDefaultVault(string $vaultName): void
    {
        $settingsPath = getcwd() . '/.keep/settings.json';
        $settings = json_decode(file_get_contents($settingsPath), true);
        $settings['default_vault'] = $vaultName;
        file_put_contents($settingsPath, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
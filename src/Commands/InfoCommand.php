<?php

namespace STS\Keep\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\table;

class InfoCommand extends BaseCommand
{
    protected function requiresInitialization(): bool
    {
        return false; // info command should work whether initialized or not
    }
    
    protected function configure(): void
    {
        $this->setName('info')
             ->setDescription('Show Keep information and status');
    }
    
    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        info('🔐  Keep Secret Management Tool');
        
        table(
            headers: ['Property', 'Value'],
            rows: [
                ['Version', $this->getApplication()->getVersion()],
                ['Working Directory', getcwd()],
                ['PHP Version', PHP_VERSION],
                ['Binary Path', $_SERVER['argv'][0] ?? 'unknown']
            ]
        );
        
        // Show Keep initialization status
        if ($this->manager->isInitialized()) {
            $settings = $this->manager->getSettings();
            
            info('📋  Configuration');
            table(
                headers: ['Setting', 'Value'],
                rows: [
                    ['App Name', $settings['app_name']],
                    ['Namespace', $settings['namespace']],
                    ['Stages', implode(', ', $settings['stages'])],
                    ['Default Vault', $settings['default_vault'] ?? 'None']
                ]
            );
            
            // Show configured vaults
            $configuredVaults = $this->manager->getConfiguredVaults();
            if (!empty($configuredVaults)) {
                info('🗄️  Configured Vaults');
                $vaultRows = [];
                foreach ($configuredVaults as $slug => $config) {
                    $isDefault = $slug === $settings['default_vault'] ? ' (default)' : '';
                    $vaultRows[] = [$slug . $isDefault, $config['name'], $config['driver']];
                }
                table(
                    headers: ['Slug', 'Name', 'Driver'],
                    rows: $vaultRows
                );
            } else {
                warning('No vaults configured');
                info('Run "keep vault:add" to add your first vault');
            }
        } else {
            warning('Keep is not initialized in this directory');
            info('Run "keep configure" to set up Keep configuration');
        }
        
        return self::SUCCESS;
    }
}
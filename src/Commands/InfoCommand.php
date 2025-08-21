<?php

namespace STS\Keep\Commands;

use STS\Keep\Facades\Keep;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\table;

class InfoCommand extends BaseCommand
{
    protected $signature = 'info';
    protected $description = 'Show Keep information and status';
    
    protected function process()
    {
        $this->showSystemInfo();
        $this->showConfiguration();
        $this->showVaults();
    }
    
    private function showSystemInfo(): void
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
    }
    
    private function showConfiguration(): void
    {
        $settings = Keep::getSettings();
        
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
    }
    
    private function showVaults(): void
    {
        $configuredVaults = Keep::getConfiguredVaults();
        
        if ($configuredVaults->isEmpty()) {
            warning('No vaults configured');
            info('Run "keep vault:add" to add your first vault');
            return;
        }
        
        info('🗄️  Configured Vaults');
        
        $settings = Keep::getSettings();
        $vaultRows = [];
        
        foreach ($configuredVaults as $slug => $config) {
            $isDefault = $slug === $settings['default_vault'] ? ' (default)' : '';
            $vaultRows[] = [$slug . $isDefault, $config->name(), $config->driver()];
        }
        
        table(
            headers: ['Slug', 'Name', 'Driver'],
            rows: $vaultRows
        );
    }
}
<?php

namespace STS\Keep\Commands;

use STS\Keep\Facades\Keep;
use function Laravel\Prompts\table;
use function Laravel\Prompts\info;

class VaultListCommand extends BaseCommand
{
    protected $signature = 'vault:list';
    protected $description = 'List all configured vaults';
    
    protected function process(): int
    {
        $configuredVaults = Keep::getConfiguredVaults();
        
        if ($configuredVaults->isEmpty()) {
            info('No vaults are configured yet.');
            info('Add your first vault with: keep vault:add');
            return self::SUCCESS;
        }
        
        $defaultVault = Keep::getSetting('default_vault');
        $rows = [];
        
        foreach ($configuredVaults as $slug => $config) {
            $isDefault = $slug === $defaultVault ? '✓' : '';
            $rows[] = [
                $slug,
                $config->name(),
                $config->driver(), 
                $isDefault
            ];
        }
        
        info('🗄️  Configured Vaults');
        table(
            headers: ['Slug', 'Name', 'Driver', 'Default'],
            rows: $rows
        );
        
        return self::SUCCESS;
    }
}
<?php

namespace STS\Keep\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\table;
use function Laravel\Prompts\info;

class VaultListCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('vault:list')
             ->setDescription('List all configured vaults');
    }
    
    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $configuredVaults = $this->manager->getConfiguredVaults();
        
        if (empty($configuredVaults)) {
            info('No vaults are configured yet.');
            info('Add your first vault with: keep vault:add');
            return self::SUCCESS;
        }
        
        $defaultVault = $this->manager->getSetting('default_vault');
        $rows = [];
        
        foreach ($configuredVaults as $slug => $config) {
            $isDefault = $slug === $defaultVault ? '✓' : '';
            $rows[] = [
                $slug,
                $config['name'] ?? 'Unknown',
                $config['driver'] ?? 'Unknown', 
                $isDefault
            ];
        }
        
        info('🗄️ Configured Vaults');
        table(
            headers: ['Slug', 'Name', 'Driver', 'Default'],
            rows: $rows
        );
        
        return self::SUCCESS;
    }
}
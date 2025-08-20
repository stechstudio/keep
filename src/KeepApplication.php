<?php

namespace STS\Keep;

use Illuminate\Support\Arr;
use STS\Keep\Enums\KeepInstall;
use Illuminate\Console\Application;
use STS\Keep\KeepContainer;
use Illuminate\Events\Dispatcher;
use Symfony\Component\Console\Input\InputDefinition;

class KeepApplication extends Application
{
    protected KeepManager $manager;

    public function __construct(protected KeepInstall $install)
    {
        // Create a minimal container for Laravel console commands
        $container = new KeepContainer();
        $container->instance(KeepContainer::class, $container);

        // Create event dispatcher
        $events = new Dispatcher($container);
        $container->instance(Dispatcher::class, $events);
        
        parent::__construct($container, $events, '1.0.0-alpha');
        $this->setName('Keep');
        
        // Initialize KeepManager with loaded configuration
        $this->manager = new KeepManager($this->loadSettings(), $this->loadVaults());
        
        // Register KeepManager in container for global access
        $container->instance(KeepManager::class, $this->manager);
        
        // Set container as global instance
        KeepContainer::setInstance($container);

        $this->add((new Commands\InfoCommand()));
        $this->add((new Commands\ConfigureCommand()));

        $this->add((new Commands\VaultAddCommand()));
        $this->add((new Commands\VaultEditCommand()));
        $this->add((new Commands\VaultListCommand()));

        $this->add((new Commands\GetCommand()));
        $this->add((new Commands\SetCommand()));
        $this->add((new Commands\ListCommand()));
        
        // TODO: Refactor these Laravel commands to Symfony Console:
        // - AbstractCommand.php (base class needs complete rewrite)
        // - ListCommand.php (needs Symfony Console refactor)  
        // - GetCommand.php (needs Symfony Console refactor)
        // - SetCommand.php (needs Symfony Console refactor)
        // - DeleteCommand.php (needs Symfony Console refactor)
        // - HistoryCommand.php (needs Symfony Console refactor)
        // - DiffCommand.php (needs Symfony Console refactor)
        // - MergeCommand.php (needs Symfony Console refactor)
        // - ExportCommand.php (needs Symfony Console refactor)
        // - ImportCommand.php (needs Symfony Console refactor)
        // - VerifyCommand.php (needs Symfony Console refactor)
        // - CopyCommand.php (needs Symfony Console refactor)
    }
    
    public function getManager(): KeepManager
    {
        return $this->manager;
    }
    
    protected function loadSettings(): array
    {
        $settingsPath = getcwd() . '/.keep/settings.json';
        
        if (!file_exists($settingsPath)) {
            return [];
        }
        
        $contents = file_get_contents($settingsPath);
        $settings = json_decode($contents, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        
        return $settings ?? [];
    }
    
    protected function loadVaults(): array
    {
        $vaultsDir = getcwd() . '/.keep/vaults';
        $vaults = [];
        
        if (!is_dir($vaultsDir)) {
            return [];
        }
        
        foreach (glob($vaultsDir . '/*.json') as $vaultFile) {
            $vaultName = basename($vaultFile, '.json');
            $contents = file_get_contents($vaultFile);
            $config = json_decode($contents, true);
            
            if (json_last_error() === JSON_ERROR_NONE && $config) {
                $vaults[$vaultName] = $config;
            }
        }
        
        return $vaults;
    }

    #[\Override]
    protected function getDefaultInputDefinition(): InputDefinition
    {
        return tap(parent::getDefaultInputDefinition(), function ($definitions) {
            $definitions->setOptions(Arr::except($definitions->getOptions(), ['env']));
        });
    }
}
<?php

namespace STS\Keep\Commands;

use Psy\Shell;
use STS\Keep\Shell\CommandExecutor;
use STS\Keep\Shell\KeepShell;
use STS\Keep\Shell\ShellContext;

class ShellCommand extends BaseCommand
{
    protected $signature = 'shell 
        {--stage= : Initial stage to use}
        {--vault= : Initial vault to use}';
    
    protected $description = 'Start an interactive shell for Keep commands';
    
    public function process()
    {
        if (!class_exists(Shell::class)) {
            $this->error('PsySH is required to run the Keep shell.');
            $this->line('Install it with: composer require psy/psysh');
            return self::FAILURE;
        }
        
        // Create context with initial settings
        $context = new ShellContext(
            $this->option('stage'),
            $this->option('vault')
        );
        
        // Create command executor
        $executor = new CommandExecutor($context, $this->getApplication());
        
        // Create and run the shell
        $shell = new KeepShell($context, $executor);
        
        try {
            $shell->run();
        } catch (\Exception $e) {
            // Handle exit gracefully
            if (!str_contains($e->getMessage(), 'Exit')) {
                throw $e;
            }
        }
        
        $this->info('Goodbye!');
        return self::SUCCESS;
    }
}
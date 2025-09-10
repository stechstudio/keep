<?php

namespace STS\Keep\Commands;

use STS\Keep\Shell\SimpleShell;
use STS\Keep\Shell\ShellContext;

class ShellCommand extends BaseCommand
{
    protected $signature = 'shell 
        {--env= : Initial environment to use}
        {--vault= : Initial vault to use}';
    
    protected $description = 'Start an interactive shell for Keep commands';
    
    public function process()
    {
        // Check if readline is available
        if (!function_exists('readline')) {
            $this->error('The readline extension is required to run the Keep shell.');
            $this->line('Please install the readline PHP extension.');
            return self::FAILURE;
        }
        
        $context = new ShellContext(
            $this->option('env'),
            $this->option('vault')
        );

        $shell = new SimpleShell($context, $this->getApplication());
        
        try {
            $shell->run();
        } catch (\Exception $e) {
            // Handle exit gracefully
            if (!str_contains($e->getMessage(), 'Exit')) {
                throw $e;
            }
        }
        
        return self::SUCCESS;
    }
}
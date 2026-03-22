<?php

namespace STS\Keep\Commands;

use STS\Keep\Facades\Keep;
use STS\Keep\Shell\SimpleShell;
use STS\Keep\Shell\ShellContext;

class ShellCommand extends BaseCommand
{
    protected $signature = 'shell
        {--env= : Initial environment to use}
        {--vault= : Initial vault to use}';

    protected $description = 'Start an interactive shell for Keep commands';

    protected function requiresInitialization(): bool
    {
        return false;
    }

    public function process()
    {
        if (!Keep::isInitialized()) {
            $this->line('');
            $this->line('<info>Welcome to Keep!</info> Secret management for your team.');
            $this->line('');

            $this->call('init');

            if (!Keep::isInitialized()) {
                return self::SUCCESS;
            }

            $this->line('');
        }

        if (!function_exists('readline')) {
            $this->error('The readline extension is required to run the Keep shell.');
            $this->line('Please install the readline PHP extension.');
            return self::FAILURE;
        }
        
        $context = new ShellContext(
            $this->option('env'),
            $this->option('vault')
        );

        /** @var \Illuminate\Console\Application $app */
        $app = $this->getApplication();
        $shell = new SimpleShell($context, $app);
        
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
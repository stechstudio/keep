<?php

namespace STS\Keep\Shell;

use Psy\Shell;
use STS\Keep\Shell\Commands\ContextCommand;
use STS\Keep\Shell\Commands\HelpCommand;
use STS\Keep\Shell\Commands\KeepProxyCommand;
use STS\Keep\Shell\Commands\ListCommand;
use STS\Keep\Shell\Commands\StageCommand;
use STS\Keep\Shell\Commands\UseCommand;
use STS\Keep\Shell\Commands\VaultCommand;
use STS\Keep\Shell\Completers;

class KeepShell extends Shell
{
    private ShellContext $context;
    private CommandExecutor $executor;
    
    public function __construct(ShellContext $context, CommandExecutor $executor)
    {
        $config = new KeepShellConfiguration($context);
        parent::__construct($config);
        
        $this->context = $context;
        $this->executor = $executor;
        
        // Add our custom shell commands
        $this->add(new StageCommand($context));
        $this->add(new VaultCommand($context));
        $this->add(new UseCommand($context));
        $this->add(new ContextCommand($context));
        
        // Register Keep commands as PsySH commands
        $this->registerKeepCommands();
        
        // Override the help and list commands with our custom versions
        $this->add(new HelpCommand());
        $this->add(new ListCommand());
        
        // Pre-load secrets to ensure they're available for tab completion
        $this->preloadSecrets($context);
    }
    
    private function preloadSecrets(ShellContext $context): void
    {
        $secrets = $context->getCachedSecretNames();
//        if (!empty($secrets)) {
//            echo "Loaded " . count($secrets) . " secrets: " . implode(', ', $secrets) . "\n";
//        } else {
//            echo "No secrets found in {$context->getVault()}:{$context->getStage()}\n";
//        }
    }
    
    /**
     * Override to only include essential PsySH commands
     */
    protected function getDefaultCommands(): array
    {
        return [
            new \Psy\Command\ExitCommand(),
            new \Psy\Command\ClearCommand(),
        ];
    }
    
    /**
     * Override to exclude default matchers that interfere with our completions
     */
    protected function getDefaultMatchers(): array
    {
        // Only return our custom matcher, no PsySH defaults
        return [
            new KeepCommandMatcher(
                new Completers\CommandCompleter(),
                new Completers\SecretCompleter($this->context),
                new Completers\StageCompleter($this->context),
                new Completers\VaultCompleter($this->context)
            ),
        ];
    }
    
    private function registerKeepCommands(): void
    {
        // Define Keep commands and their aliases
        // Note: Configuration commands (configure, vault:add, stage:add, etc.) 
        // are intentionally excluded from the shell - use the standalone CLI for those
        $commands = [
            'get' => ['g'],
            'set' => ['s'],
            'delete' => ['d'],
            'show' => ['l', 'ls'],
            'copy' => [],
            'export' => [],
            'diff' => [],
            'verify' => [],
            'info' => [],
            'history' => [],
        ];
        
        foreach ($commands as $command => $aliases) {
            $this->add(new KeepProxyCommand($this->executor, $command, $aliases));
        }
    }
}
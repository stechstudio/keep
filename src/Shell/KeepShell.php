<?php

namespace STS\Keep\Shell;

use Psy\Shell;
use STS\Keep\Shell\Commands\ContextCommand;
use STS\Keep\Shell\Commands\StageCommand;
use STS\Keep\Shell\Commands\UseCommand;
use STS\Keep\Shell\Commands\VaultCommand;

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
    }
    
    /**
     * Override the default input handling to execute Keep commands
     */
    public function beforeLoop(): void
    {
        $this->writeStartupMessage();
    }
    
    protected function writeStartupMessage(): void
    {
        $this->output->writeln('');
        $this->output->writeln('<info>Welcome to Keep Shell v1.0.0</info>');
        $this->output->writeln("Type 'help' for available commands or 'exit' to quit.");
        $this->output->writeln("Current context: <comment>{$this->context->getVault()}:{$this->context->getStage()}</comment>");
        $this->output->writeln("<comment>Tab completion is available for commands and secret names!</comment>");
        $this->output->writeln('');
    }
    
    /**
     * Override to handle Keep commands that aren't PsySH commands
     */
    public function writeException(\Throwable $e): void
    {
        // Check if this is just an undefined constant (our Keep command)
        if ($e instanceof \ErrorException && str_contains($e->getMessage(), 'Undefined constant')) {
            // This is likely a Keep command, not an error
            return;
        }
        
        parent::writeException($e);
    }
    
    /**
     * Process input - intercept for Keep commands
     */
    public function getInput(bool $interactive = true): string
    {
        $input = parent::getInput($interactive);
        
        // Handle Keep commands
        if ($this->isKeepCommand($input)) {
            $this->executor->execute($input);
            // Return empty to prevent PsySH from trying to evaluate
            return '';
        }
        
        return $input;
    }
    
    private function isKeepCommand(string $input): bool
    {
        if (empty(trim($input))) {
            return false;
        }
        
        // Check if it's a known Keep command
        $firstWord = explode(' ', trim($input))[0];
        
        $keepCommands = [
            'set', 'get', 'delete', 'show', 'copy', 'import', 'export', 'diff',
            'configure', 'verify', 'info', 'history',
            'g', 'd', 'l', 'ls', 'list',
        ];
        
        return in_array($firstWord, $keepCommands);
    }
}
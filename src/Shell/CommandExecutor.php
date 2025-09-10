<?php

namespace STS\Keep\Shell;

use Illuminate\Console\Application;
use STS\Keep\Shell\Commands\InteractiveExport;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class CommandExecutor
{
    
    public function __construct(
        private ShellContext $context,
        private Application $application
    ) {
    }
    
    public function execute(string $input): int
    {
        $input = trim($input);
        
        if (empty($input)) {
            return 0;
        }
        
        try {
            $parsed = $this->parseInput($input);
            $commandName = $parsed['command'];
            
            // Handle interactive commands (like export) with special flow
            if (CommandRegistry::isInteractive($commandName)) {
                return $this->runInteractiveCommand($commandName, $parsed['positionals']);
            }
            
            $this->validateCommand($commandName);
            
            $exitCode = $this->runCommand($commandName, $parsed);
            
            if (CommandRegistry::isWriteCommand($parsed['command'])) {
                $this->context->invalidateCache();
            }
            
            return $exitCode;
        } catch (\Exception $e) {
            $this->outputError($e->getMessage());
            return 1;
        }
    }
    
    protected function parseInput(string $input): array
    {
        $parts = str_getcsv($input, ' ', '"', '\\');
        $command = array_shift($parts);
        
        return [
            'command' => CommandRegistry::resolveAlias($command),
            'positionals' => $parts,
        ];
    }
    
    protected function validateCommand(string $commandName): void
    {
        // Get the CLI command name (may be different from shell command)
        $cliCommandName = CommandRegistry::getCliCommand($commandName);
        
        if (!$this->application->has($cliCommandName)) {
            throw new \Exception("Unknown command: {$commandName}");
        }
    }
    
    protected function runCommand(string $commandName, array $parsed): int
    {
        // Get the CLI command name (may be different from shell command)
        $cliCommandName = CommandRegistry::getCliCommand($commandName);
        
        $input = $this->buildCommandInput($cliCommandName, $parsed);
        $output = new ConsoleOutput();
        
        $command = $this->application->find($cliCommandName);
        
        if (method_exists($command, 'resetInput')) {
            $command->resetInput();
        }
        
        $exitCode = $command->run(new ArrayInput($input), $output);
        return $exitCode === null ? 0 : $exitCode;
    }
    
    protected function buildCommandInput(string $commandName, array $parsed): array
    {
        $input = ['command' => $commandName];
        
        // Use the new ArgumentProcessor for clean argument handling
        ArgumentProcessor::process($parsed['command'], $parsed['positionals'], $input);
        
        // Add context (vault/env) if needed
        $this->addContextIfNeeded($parsed['command'], $input);
        
        return $input;
    }
    
    
    protected function addContextIfNeeded(string $command, array &$input): void
    {
        if (CommandRegistry::requiresEnv($command) && !isset($input['--env'])) {
            $input['--env'] = $this->context->getEnv();
        }
        
        if (CommandRegistry::requiresVault($command) && !isset($input['--vault'])) {
            $input['--vault'] = $this->context->getVault();
        }
        
        // Copy command always uses current context as 'from'
        if ($command === 'copy') {
            $input['--from'] = sprintf(
                "%s:%s",
                $this->context->getVault(),
                $this->context->getEnv()
            );
        }
    }
    
    protected function outputError(string $message): void
    {
        $output = new ConsoleOutput();
        $output->writeln("<error>{$message}</error>");
    }
    
    /**
     * Run an interactive command (like export)
     */
    protected function runInteractiveCommand(string $command, array $args): int
    {
        // Currently only export is interactive, but this is extensible
        return match ($command) {
            'export' => (new InteractiveExport($this->context, $this->application))->execute($args),
            default => throw new \Exception("Unknown interactive command: {$command}")
        };
    }
}
<?php

namespace STS\Keep\Shell;

use Illuminate\Console\Application;
use STS\Keep\Shell\Commands\InteractiveExport;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class CommandExecutor
{
    private const COMMAND_ALIASES = [
        'g' => 'get',
        's' => 'set',
        'd' => 'delete',
        'l' => 'show',
        'ls' => 'show',
    ];
    
    private const NO_STAGE_COMMANDS = ['diff', 'copy', 'info', 'verify'];
    private const NO_VAULT_COMMANDS = ['export', 'copy', 'info', 'verify'];
    private const WRITE_COMMANDS = ['set', 'delete', 'copy', 'import', 'rename'];
    
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
            
            // Handle export command specially with interactive flow
            if ($commandName === 'export') {
                return $this->runInteractiveExport($parsed['positionals']);
            }
            
            $this->validateCommand($commandName);
            
            $exitCode = $this->runCommand($commandName, $parsed);
            
            if ($this->isWriteCommand($parsed['command'])) {
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
        
        // In the interactive shell, we treat everything as positional arguments
        // No -- prefix support for better UX
        $positionals = $parts;
        $options = [];
        
        return [
            'command' => $this->mapAlias($command),
            'positionals' => $positionals,
            'options' => $options,
        ];
    }
    
    
    protected function mapAlias(string $command): string
    {
        return self::COMMAND_ALIASES[$command] ?? $command;
    }
    
    protected function validateCommand(string $commandName): void
    {
        if (!$this->application->has($commandName)) {
            throw new \Exception("Unknown command: {$commandName}");
        }
    }
    
    protected function runCommand(string $commandName, array $parsed): int
    {
        $input = $this->buildCommandInput($commandName, $parsed);
        $output = new ConsoleOutput();
        
        $command = $this->application->find($commandName);
        
        if (method_exists($command, 'resetInput')) {
            $command->resetInput();
        }
        
        $exitCode = $command->run(new ArrayInput($input), $output);
        return $exitCode === null ? 0 : $exitCode;
    }
    
    protected function buildCommandInput(string $commandName, array $parsed): array
    {
        $input = ['command' => $commandName];
        
        // Process positionals and options based on command
        $positionals = $this->processPositionalArguments(
            $parsed['command'],
            $parsed['positionals'],
            $input
        );
        
        $this->addOptions($parsed['options'], $input);
        
        $this->addContextIfNeeded($parsed['command'], $parsed['options'], $input);
        
        return $input;
    }
    
    protected function processPositionalArguments(string $command, array $positionals, array &$input): array
    {
        switch ($command) {
            case 'set':
                $this->addIfExists($positionals, 0, 'key', $input);
                $this->addIfExists($positionals, 1, 'value', $input);
                break;
                
            case 'get':
            case 'history':
                // Just take the key, ignore any format options (shell is for humans)
                $this->addIfExists($positionals, 0, 'key', $input);
                break;
                
            case 'export':
                // Export is handled by InteractiveExport, this shouldn't be reached
                break;
                
            case 'copy':
                $this->addIfExists($positionals, 0, 'key', $input);
                // Handle optional destination argument
                if (isset($positionals[1])) {
                    $input['--to'] = $positionals[1];
                }
                break;
                
            case 'import':
                $this->addIfExists($positionals, 0, 'file', $input);
                break;
                
            case 'stage:add':
                $this->addIfExists($positionals, 0, 'name', $input);
                break;
                
            case 'show':
                // Only support 'unmask' in interactive shell (no format options)
                if (in_array('unmask', $positionals)) {
                    $input['--unmask'] = true;
                }
                // Don't pass any positionals to show command
                $positionals = [];
                break;
                
            case 'delete':
                // Handle "delete KEY force" without -- prefix
                foreach ($positionals as $index => $arg) {
                    if ($arg === 'force' && $index > 0) {
                        $input['--force'] = true;
                        unset($positionals[$index]);
                    }
                }
                // First positional is the key
                $this->addIfExists(array_values($positionals), 0, 'key', $input);
                break;
                
            case 'rename':
                // Handle "rename OLD NEW [force]" without -- prefix
                $this->addIfExists($positionals, 0, 'old', $input);
                $this->addIfExists($positionals, 1, 'new', $input);
                if (in_array('force', $positionals)) {
                    $input['--force'] = true;
                }
                break;
                
            case 'search':
                // Handle "search QUERY [unmask] [case-sensitive]" without -- prefix
                $this->addIfExists($positionals, 0, 'query', $input);
                if (in_array('unmask', $positionals)) {
                    $input['--unmask'] = true;
                }
                if (in_array('case-sensitive', $positionals)) {
                    $input['--case-sensitive'] = true;
                }
                break;
        }
        
        return $positionals;
    }
    
    protected function addIfExists(array $array, int $index, string $key, array &$target): void
    {
        if (isset($array[$index])) {
            $target[$key] = $array[$index];
        }
    }
    
    protected function addOptions(array $options, array &$input): void
    {
        foreach ($options as $key => $value) {
            $input['--' . $key] = $value;
        }
    }
    
    protected function addContextIfNeeded(string $command, array $options, array &$input): void
    {
        $needsStage = !in_array($command, self::NO_STAGE_COMMANDS);
        $needsVault = !in_array($command, self::NO_VAULT_COMMANDS);
        
        if ($needsStage && !isset($options['stage'])) {
            $input['--stage'] = $this->context->getStage();
        }
        
        if ($needsVault && !isset($options['vault'])) {
            $input['--vault'] = $this->context->getVault();
        }
        
        // Special handling for copy command
        if ($command === 'copy' && !isset($options['from'])) {
            $input['--from'] = sprintf(
                "%s:%s",
                $this->context->getVault(),
                $this->context->getStage()
            );
        }
    }
    
    protected function isWriteCommand(string $command): bool
    {
        return in_array($command, self::WRITE_COMMANDS);
    }
    
    protected function outputError(string $message): void
    {
        $output = new ConsoleOutput();
        $output->writeln("<error>{$message}</error>");
    }
    
    /**
     * Run the interactive export command
     */
    protected function runInteractiveExport(array $args): int
    {
        $interactiveExport = new InteractiveExport($this->context, $this->application);
        return $interactiveExport->execute($args);
    }
}
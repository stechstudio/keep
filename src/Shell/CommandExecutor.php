<?php

namespace STS\Keep\Shell;

use Illuminate\Console\Application;
use STS\Keep\Facades\Keep;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class CommandExecutor
{
    private ShellContext $context;
    private Application $application;
    
    public function __construct(ShellContext $context, Application $application)
    {
        $this->context = $context;
        $this->application = $application;
    }
    
    public function execute(string $input): int
    {
        $input = trim($input);
        
        if (empty($input)) {
            return 0;
        }
        
        // Parse the command
        $parts = str_getcsv($input, ' ', '"', '\\');
        $command = array_shift($parts);
        
        // Map shortcuts to full commands
        $command = $this->mapShortcut($command);
        
        try {
            // Build the full command with context
            $fullCommand = $this->buildFullCommand($command, $parts);
            
            // Find the command
            $commandName = $this->resolveCommandName($command);
            if (!$this->application->has($commandName)) {
                throw new \Exception("Unknown command: {$command}");
            }
            
            // Create input/output
            $input = new ArrayInput($fullCommand);
            $output = new ConsoleOutput();
            
            // Find the command and reset any cached input
            $commandInstance = $this->application->find($commandName);
            if (method_exists($commandInstance, 'resetInput')) {
                $commandInstance->resetInput();
            }
            
            // Run the command
            $exitCode = $commandInstance->run($input, $output);
            
            // Invalidate cache if it's a write operation
            if ($this->isWriteCommand($command)) {
                $this->context->invalidateCache();
            }
            
            return $exitCode ?? 0;
            
        } catch (\Exception $e) {
            $output = new ConsoleOutput();
            $output->writeln("<error>{$e->getMessage()}</error>");
            return 1;
        }
    }
    
    private function mapShortcut(string $command): string
    {
        return match($command) {
            'g' => 'get',
            's' => 'set',
            'd' => 'delete',
            'l', 'ls' => 'show',
            default => $command
        };
    }
    
    private function buildFullCommand(string $command, array $args): array
    {
        $fullCommand = ['command' => $this->resolveCommandName($command)];
        
        // Commands that don't need context
        $noStageCommands = ['configure', 'vault:add', 'vault:list', 'stage:add', 'diff'];
        $noVaultCommands = ['configure', 'vault:add', 'vault:list', 'stage:add', 'import', 'export'];
        
        $needsStage = !in_array($command, $noStageCommands);
        $needsVault = !in_array($command, $noVaultCommands);
        
        // Parse arguments and options
        $positionals = [];
        $options = [];
        
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                if (str_contains($arg, '=')) {
                    [$key, $value] = explode('=', $arg, 2);
                    $options[substr($key, 2)] = $value;
                } else {
                    $options[substr($arg, 2)] = true;
                }
            } else {
                $positionals[] = $arg;
            }
        }
        
        // Add positional arguments based on command
        $this->addPositionalArguments($command, $positionals, $fullCommand);
        
        // Add options
        foreach ($options as $key => $value) {
            $fullCommand['--' . $key] = $value;
        }
        
        // Add context if not specified
        if ($needsStage && !isset($options['stage'])) {
            $fullCommand['--stage'] = $this->context->getStage();
        }
        if ($needsVault && !isset($options['vault'])) {
            $fullCommand['--vault'] = $this->context->getVault();
        }
        
        // Special handling for copy command
        if ($command === 'copy' && !isset($options['from'])) {
            $fullCommand['--from'] = sprintf(
                "%s:%s",
                $this->context->getVault(),
                $this->context->getStage()
            );
        }
        
        return $fullCommand;
    }
    
    private function addPositionalArguments(string $command, array $positionals, array &$fullCommand): void
    {
        switch ($command) {
            case 'set':
                if (isset($positionals[0])) {
                    $fullCommand['key'] = $positionals[0];
                }
                if (isset($positionals[1])) {
                    $fullCommand['value'] = $positionals[1];
                }
                break;
                
            case 'get':
            case 'delete':
            case 'history':
                if (isset($positionals[0])) {
                    $fullCommand['key'] = $positionals[0];
                }
                break;
                
            case 'copy':
                if (isset($positionals[0])) {
                    $fullCommand['key'] = $positionals[0];
                }
                break;
                
            case 'import':
                if (isset($positionals[0])) {
                    $fullCommand['file'] = $positionals[0];
                }
                break;
                
            case 'stage:add':
                if (isset($positionals[0])) {
                    $fullCommand['name'] = $positionals[0];
                }
                break;
        }
    }
    
    private function resolveCommandName(string $command): string
    {
        // Handle commands with colons
        if (str_contains($command, ':')) {
            return $command;
        }
        
        // Map to actual command names
        return match($command) {
            'configure' => 'configure',
            'set' => 'set',
            'get' => 'get',
            'show' => 'show',
            'delete' => 'delete',
            'copy' => 'copy',
            'import' => 'import',
            'export' => 'export',
            'diff' => 'diff',
            'verify' => 'verify',
            'info' => 'info',
            'history' => 'history',
            default => $command
        };
    }
    
    private function isWriteCommand(string $command): bool
    {
        $writeCommands = ['set', 'delete', 'copy', 'import'];
        return in_array($command, $writeCommands);
    }
}
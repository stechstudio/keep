<?php

namespace STS\Keep\Shell;

use Illuminate\Console\Application;
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
    private const WRITE_COMMANDS = ['set', 'delete', 'copy', 'import'];
    
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
            $commandName = $this->resolveCommand($parsed['command']);
            
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
        
        $positionals = [];
        $options = [];
        
        foreach ($parts as $arg) {
            if (str_starts_with($arg, '--')) {
                $this->parseOption($arg, $options);
            } else {
                $positionals[] = $arg;
            }
        }
        
        return [
            'command' => $this->mapAlias($command),
            'positionals' => $positionals,
            'options' => $options,
        ];
    }
    
    protected function parseOption(string $arg, array &$options): void
    {
        $arg = substr($arg, 2); // Remove --
        
        if (str_contains($arg, '=')) {
            [$key, $value] = explode('=', $arg, 2);
            $options[$key] = $value;
        } else {
            $options[$arg] = true;
        }
    }
    
    protected function mapAlias(string $command): string
    {
        return self::COMMAND_ALIASES[$command] ?? $command;
    }
    
    protected function resolveCommand(string $command): string
    {
        if (str_contains($command, ':')) {
            return $command;
        }
        
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
        
        $this->addPositionalArguments(
            $parsed['command'],
            $parsed['positionals'],
            $input
        );
        
        $this->addOptions($parsed['options'], $input);
        
        $this->addContextIfNeeded($parsed['command'], $parsed['options'], $input);
        
        return $input;
    }
    
    protected function addPositionalArguments(string $command, array $positionals, array &$input): void
    {
        switch ($command) {
            case 'set':
                $this->addIfExists($positionals, 0, 'key', $input);
                $this->addIfExists($positionals, 1, 'value', $input);
                break;
                
            case 'get':
            case 'delete':
            case 'history':
                $this->addIfExists($positionals, 0, 'key', $input);
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
                // Handle "show unmask" as a convenience (without -- prefix)
                foreach ($positionals as $arg) {
                    if ($arg === 'unmask') {
                        $input['--unmask'] = true;
                    }
                }
                break;
        }
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
}
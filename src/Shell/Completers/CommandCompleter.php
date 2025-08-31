<?php

namespace STS\Keep\Shell\Completers;

class CommandCompleter
{
    private array $commands = [
        // Keep commands
        'set', 'get', 'delete', 'show', 'copy', 'export', 'diff',
        'verify', 'info', 'history',
        
        // Shell commands
        'stage', 'vault', 'use', 'context', 'help', 'clear', 'exit', 'quit',
        
        // Aliases
        's', 'v', 'u', 'ctx', 'g', 'd', 'l', 'ls', 'list', 'h', '?', 'cls', 'q'
    ];
    
    private array $commandDescriptions = [
        'set' => 'Set a secret value',
        'get' => 'Get a secret value',
        'delete' => 'Delete a secret',
        'show' => 'List all secrets',
        'copy' => 'Copy secrets between stages/vaults',
        'import' => 'Import secrets from file',
        'export' => 'Export secrets to file',
        'diff' => 'Compare secrets across stages/vaults',
        'stage' => 'Switch to a different stage',
        'vault' => 'Switch to a different vault',
        'use' => 'Switch both vault and stage',
        'context' => 'Show current context',
        'help' => 'Show available commands',
        'exit' => 'Exit the shell',
    ];
    
    public function complete(string $input): array
    {
        if (empty($input)) {
            return $this->commands;
        }
        
        $matches = array_filter($this->commands, function($cmd) use ($input) {
            return str_starts_with($cmd, $input);
        });
        
        return array_values($matches);
    }
    
    public function getCommandDescription(string $command): ?string
    {
        return $this->commandDescriptions[$command] ?? null;
    }
    
    public function isShellCommand(string $command): bool
    {
        $shellCommands = [
            'stage', 's', 'vault', 'v', 'use', 'u', 
            'context', 'ctx', 'help', '?', 'clear', 'cls', 
            'exit', 'quit', 'q', 'history', 'h', 'ls', 'list'
        ];
        
        return in_array($command, $shellCommands);
    }
    
    public function getAllCommands(): array
    {
        return $this->commands;
    }
}
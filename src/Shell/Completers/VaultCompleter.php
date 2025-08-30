<?php

namespace STS\Keep\Shell\Completers;

use STS\Keep\Shell\ShellContext;

class VaultCompleter
{
    private ShellContext $context;
    
    public function __construct(ShellContext $context)
    {
        $this->context = $context;
    }
    
    public function complete(string $input, string $command = ''): array
    {
        // Provide vault completion for relevant commands
        $vaultCommands = ['vault', 'v', '--vault'];
        
        $isVaultContext = in_array($command, $vaultCommands)
            || str_contains($input, '--vault=');
            
        if (!$isVaultContext && $command !== 'vault' && $command !== 'v') {
            return [];
        }
        
        $vaults = $this->context->getAvailableVaults();
        
        if (empty($input)) {
            return $vaults;
        }
        
        $matches = array_filter($vaults, function($vault) use ($input) {
            return stripos($vault, $input) === 0;
        });
        
        return array_values($matches);
    }
}
<?php

namespace STS\Keep\Shell\Completers;

use STS\Keep\Shell\ShellContext;

class SecretCompleter
{
    private ShellContext $context;
    
    public function __construct(ShellContext $context)
    {
        $this->context = $context;
    }
    
    public function complete(string $input, string $command = ''): array
    {
        // Only provide secret completion for relevant commands
        $secretCommands = ['get', 'g', 'delete', 'd', 'set', 'copy', 'history'];
        
        if (!in_array($command, $secretCommands)) {
            return [];
        }
        
        $secrets = $this->context->getCachedSecretNames();
        
        // If we couldn't load secrets and there's input, return empty to prevent
        // PsySH from falling back to PHP constants
        if (empty($secrets) && !empty($input)) {
            return [];
        }
        
        if (empty($input)) {
            return $secrets;
        }
        
        $matches = array_filter($secrets, function($secret) use ($input) {
            return stripos($secret, $input) === 0;
        });
        
        return array_values($matches);
    }
}
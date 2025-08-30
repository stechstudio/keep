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
        
        if (empty($input)) {
            return $secrets;
        }
        
        $matches = array_filter($secrets, function($secret) use ($input) {
            return stripos($secret, $input) === 0;
        });
        
        return array_values($matches);
    }
}
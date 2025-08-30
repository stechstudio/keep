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
        $secretCommands = ['get', 'g', 'delete', 'd', 'set', 's', 'copy', 'history'];
        
        error_log("SecretCompleter::complete - command='$command', input='$input'");
        
        if (!in_array($command, $secretCommands)) {
            error_log("SecretCompleter: Command '$command' not a secret command");
            return [];
        }
        
        $secrets = $this->context->getCachedSecretNames();
        error_log("SecretCompleter: Found " . count($secrets) . " cached secrets: " . json_encode($secrets));
        
        if (empty($secrets)) {
            error_log("SecretCompleter: No secrets available");
            return [];
        }
        
        if (empty($input)) {
            error_log("SecretCompleter: Returning all secrets for empty input");
            return $secrets;
        }
        
        $matches = array_filter($secrets, function($secret) use ($input) {
            return stripos($secret, $input) === 0;
        });
        
        $result = array_values($matches);
        error_log("SecretCompleter: Filtered to " . count($result) . " matches: " . json_encode($result));
        
        return $result;
    }
}
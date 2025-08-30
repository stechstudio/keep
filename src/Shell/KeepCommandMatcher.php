<?php

namespace STS\Keep\Shell;

use Psy\TabCompletion\Matcher\AbstractMatcher;
use STS\Keep\Shell\Completers\CommandCompleter;
use STS\Keep\Shell\Completers\SecretCompleter;
use STS\Keep\Shell\Completers\StageCompleter;
use STS\Keep\Shell\Completers\VaultCompleter;

class KeepCommandMatcher extends AbstractMatcher
{
    private CommandCompleter $commandCompleter;
    private SecretCompleter $secretCompleter;
    private StageCompleter $stageCompleter;
    private VaultCompleter $vaultCompleter;
    
    public function __construct(
        CommandCompleter $commandCompleter,
        SecretCompleter $secretCompleter,
        StageCompleter $stageCompleter,
        VaultCompleter $vaultCompleter
    ) {
        $this->commandCompleter = $commandCompleter;
        $this->secretCompleter = $secretCompleter;
        $this->stageCompleter = $stageCompleter;
        $this->vaultCompleter = $vaultCompleter;
    }
    
    /**
     * Check whether this matcher can provide completions for the current input
     */
    public function hasMatched(array $tokens): bool
    {
        // Build the full input from tokens
        $fullInput = $this->buildFullInput($tokens);
        
        // Check if this looks like a Keep command
        $keepCommands = ['get', 'g', 'set', 's', 'delete', 'd', 'show', 'l', 'ls', 
                        'copy', 'import', 'export', 'diff', 'verify', 'info', 
                        'history', 'configure', 'stage', 'vault', 'use', 'context'];
        
        // Split the input to get the first word
        $parts = preg_split('/\s+/', trim($fullInput), 2);
        $firstWord = $parts[0] ?? '';
        
        // Check if the first word is a Keep command
        return in_array($firstWord, $keepCommands);
    }
    
    /**
     * Get completions for the current input
     */
    public function getMatches(array $tokens, array $info = []): array
    {
        $fullInput = $this->buildFullInput($tokens);
        $parts = preg_split('/\s+/', trim($fullInput));
        
        // If we're at the beginning, complete commands
        if (count($parts) === 1 && !str_ends_with($fullInput, ' ')) {
            return $this->commandCompleter->complete($parts[0]);
        }
        
        $command = $parts[0];
        
        // Determine what we're completing
        $currentArg = '';
        if (str_ends_with($fullInput, ' ')) {
            // Starting a new argument
            $currentArg = '';
        } else {
            // Completing the last partial argument
            $currentArg = end($parts);
        }
        
        // Check what type of completion we need
        if ($this->isStageContext($command, $currentArg)) {
            return $this->stageCompleter->complete($this->extractValue($currentArg), $command);
        }
        
        if ($this->isVaultContext($command, $currentArg)) {
            return $this->vaultCompleter->complete($this->extractValue($currentArg), $command);
        }
        
        if ($this->isSecretContext($command)) {
            return $this->secretCompleter->complete($currentArg, $command);
        }
        
        return [];
    }
    
    /**
     * Build full input string from tokens
     */
    private function buildFullInput(array $tokens): string
    {
        $input = '';
        foreach ($tokens as $token) {
            if (is_array($token)) {
                // Skip PHP opening tag
                if ($token[0] === T_OPEN_TAG) {
                    continue;
                }
                $input .= $token[1];
            } else {
                $input .= $token;
            }
        }
        return trim($input);
    }
    
    private function isStageContext(string $command, string $arg): bool
    {
        return in_array($command, ['stage', 's']);
    }
    
    private function isVaultContext(string $command, string $arg): bool
    {
        return in_array($command, ['vault', 'v']);
    }
    
    private function isSecretContext(string $command): bool
    {
        $secretCommands = ['get', 'g', 'delete', 'd', 'set', 'copy', 'history'];
        return in_array($command, $secretCommands);
    }
    
    private function extractValue(string $arg): string
    {
        return $arg;
    }
}
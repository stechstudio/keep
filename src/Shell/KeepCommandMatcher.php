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
    
    private static array $keepCommands = [
        'get', 'g', 'set', 's', 'delete', 'd', 'show', 'l', 'ls', 
        'copy', 'import', 'export', 'diff', 'verify', 'info', 
        'history', 'configure', 'stage', 'vault', 'use', 'context'
    ];
    
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
        // Always claim to match to prevent fallback to PsySH's default matchers
        return true;
    }
    
    /**
     * Get completions for the current input
     */
    public function getMatches(array $tokens, array $info = []): array
    {
        $input = $this->getInput($tokens);
        
        // Check if input ends with space(s) BEFORE splitting
        $endsWithSpace = preg_match('/\s+$/', $input);
        
        // Now split to get parts
        $parts = preg_split('/\s+/', trim($input), -1, PREG_SPLIT_NO_EMPTY);
        
        error_log("KeepCommandMatcher::getMatches - input='$input', parts=" . json_encode($parts) . ", ends_with_space=" . ($endsWithSpace ? 'true' : 'false'));
        
        // If empty or completing the first word (command)
        if (empty($parts) || (count($parts) === 1 && !$endsWithSpace)) {
            $partial = $parts[0] ?? '';
            error_log("Completing command with partial='$partial'");
            return $this->commandCompleter->complete($partial);
        }
        
        $command = $parts[0];
        
        // Only proceed if this is a Keep command
        if (!in_array($command, self::$keepCommands)) {
            error_log("Not a Keep command: '$command'");
            return [];
        }
        
        // Determine what we're completing
        $currentArg = '';
        if ($endsWithSpace) {
            // Starting a new argument
            $currentArg = '';
        } else {
            // Completing the last partial argument
            $currentArg = end($parts);
        }
        
        error_log("Command='$command', currentArg='$currentArg'");
        
        // Check what type of completion we need
        if ($this->isStageContext($command, $currentArg)) {
            error_log("Stage context detected");
            return $this->stageCompleter->complete($currentArg, $command);
        }
        
        if ($this->isVaultContext($command, $currentArg)) {
            error_log("Vault context detected");
            return $this->vaultCompleter->complete($currentArg, $command);
        }
        
        if ($this->isSecretContext($command)) {
            error_log("Secret context detected for command '$command'");
            $matches = $this->secretCompleter->complete($currentArg, $command);
            error_log("Secret completer returned: " . json_encode($matches));
            return $matches;
        }
        
        error_log("No context matched");
        return [];
    }
    
    /**
     * Get input string from tokens
     */
    protected function getInput(array $tokens): string
    {
        $input = '';
        foreach ($tokens as $token) {
            if (is_array($token)) {
                // Skip PHP opening tag and closing tag
                if (isset($token[0]) && in_array($token[0], [T_OPEN_TAG, T_CLOSE_TAG])) {
                    continue;
                }
                $input .= $token[1] ?? '';
            } else {
                $input .= $token;
            }
        }
        
        // Remove any PHP tags that might have snuck in
        $input = str_replace(['<?php', '<?', '?>'], '', $input);
        
        // IMPORTANT: Don't trim! We need to preserve trailing spaces
        // to know if we're starting a new argument
        return $input;
    }
    
    private function isStageContext(string $command, string $arg): bool
    {
        return in_array($command, ['stage']);
    }
    
    private function isVaultContext(string $command, string $arg): bool
    {
        return in_array($command, ['vault']);
    }
    
    private function isSecretContext(string $command): bool
    {
        $secretCommands = ['get', 'g', 'delete', 'd', 'set', 's', 'copy', 'history'];
        return in_array($command, $secretCommands);
    }
}
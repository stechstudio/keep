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
        // or readline's filesystem completion
        // Always claim to match to prevent fallback to PsySH's default matchers
        // or readline's filesystem completion
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
        
        // Check if input starts with a Keep command 
        // This handles cases like "getN" or even "get N" when given as single token
        $command = null;
        $currentArg = '';
        
        // Sort commands by length (longest first) to avoid false matches
        $sortedCommands = self::$keepCommands;
        usort($sortedCommands, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        
        foreach ($sortedCommands as $cmd) {
            if ($input === $cmd) {
                // Exact match of command - show available arguments
                $command = $cmd;
                $currentArg = '';
                //error_log("KeepCommandMatcher: Exact match for command '$cmd'");
                break;
            } elseif (str_starts_with($input, $cmd)) {
                $afterCmd = substr($input, strlen($cmd));
                
                // Check what comes after the command
                if (strlen($afterCmd) === 0) {
                    // This shouldn't happen since we checked exact match above
                    continue;
                } elseif (str_starts_with($afterCmd, ' ')) {
                    // Command followed by space and maybe more: "get " or "get N"
                    $command = $cmd;
                    $currentArg = ltrim($afterCmd);
                    //error_log("KeepCommandMatcher: Command '$cmd' with space, arg='$currentArg'");
                    break;
                } else {
                    // Command runs directly into text: "getN"
                    $command = $cmd;
                    $currentArg = $afterCmd;
                    //error_log("KeepCommandMatcher: Command '$cmd' no space, arg='$currentArg'");
                    break;
                }
            }
        }
        
        // If we didn't find a combined command+arg, parse normally
        if ($command === null) {
            // Split to get parts
            $parts = preg_split('/\s+/', trim($input), -1, PREG_SPLIT_NO_EMPTY);
            
            //error_log("KeepCommandMatcher::getMatches - input='$input', parts=" . json_encode($parts) . ", ends_with_space=" . ($endsWithSpace ? 'true' : 'false'));
            
            // If empty or completing the first word (command)
            if (empty($parts) || (count($parts) === 1 && !$endsWithSpace)) {
                $partial = $parts[0] ?? '';
                //error_log("Completing command with partial='$partial'");
                return $this->commandCompleter->complete($partial);
            }
            
            $command = $parts[0];
            
            // Only proceed if this is a Keep command
            if (!in_array($command, self::$keepCommands)) {
                // Don't return empty - this causes PsySH to fall back to filesystem completion
                // Instead, return no matches explicitly
                return [];
            }
            
            // Determine what we're completing
            if ($endsWithSpace) {
                // Starting a new argument
                $currentArg = '';
            } else {
                // Completing the last partial argument
                $currentArg = end($parts);
            }
        }
        
        //error_log("Command='$command', currentArg='$currentArg'");
        
        // Check what type of completion we need
        if ($this->isStageContext($command, $currentArg)) {
            //error_log("Stage context detected");
            return $this->stageCompleter->complete($currentArg, $command);
        }
        
        if ($this->isVaultContext($command, $currentArg)) {
            //error_log("Vault context detected");
            return $this->vaultCompleter->complete($currentArg, $command);
        }
        
        if ($this->isSecretContext($command)) {
            //error_log("Secret context detected for command '$command', currentArg='$currentArg'");
            $matches = $this->secretCompleter->complete($currentArg, $command);
            //error_log("Secret completer returned: " . json_encode($matches));
            // If we have matches, return them
            if (!empty($matches)) {
                //error_log("KeepCommandMatcher::getMatches - returning matches: " . json_encode($matches));
                return $matches;
            }
            // No matches - return the current arg to block filesystem completion
            // This tells readline "the completion is what you already typed"
            $result = $currentArg ? [$currentArg] : [''];
            //error_log("KeepCommandMatcher::getMatches - no matches, blocking with: " . json_encode($result));
            return $result;
        }
        
        //error_log("No context matched, command='$command'");
        // For any Keep command, block filesystem completion
        if (in_array($command, self::$keepCommands)) {
            // Return current arg to block filesystem completion
            $result = $currentArg ? [$currentArg] : [''];
            //error_log("KeepCommandMatcher::getMatches - blocking with: " . json_encode($result) . " for Keep command");
            return $result;
        }
        //error_log("KeepCommandMatcher::getMatches - returning [] for non-Keep command");
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
        
        // Log raw input for debugging
        //error_log("KeepCommandMatcher::getInput - raw tokens: " . json_encode($tokens) . " => input: '$input'");
        
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
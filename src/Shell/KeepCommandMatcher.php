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
        // We always want to provide completions
        return true;
    }
    
    /**
     * Get completions for the current input
     */
    public function getMatches(array $tokens, array $info = []): array
    {
        $input = $this->getInput($tokens);
        $parts = explode(' ', trim($input));
        
        // If we're at the beginning, complete commands
        if (count($parts) === 1) {
            return $this->commandCompleter->complete($parts[0]);
        }
        
        $command = $parts[0];
        $currentArg = end($parts);
        
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
        
        // Check for option completion
        if (str_starts_with($currentArg, '--')) {
            return $this->getOptionCompletions($command, $currentArg);
        }
        
        return [];
    }
    
    private function isStageContext(string $command, string $arg): bool
    {
        return in_array($command, ['stage', 's']) 
            || str_starts_with($arg, '--stage=')
            || str_starts_with($arg, '--to=')
            || str_starts_with($arg, '--from=');
    }
    
    private function isVaultContext(string $command, string $arg): bool
    {
        return in_array($command, ['vault', 'v'])
            || str_starts_with($arg, '--vault=');
    }
    
    private function isSecretContext(string $command): bool
    {
        $secretCommands = ['get', 'g', 'delete', 'd', 'set', 'copy', 'history'];
        return in_array($command, $secretCommands);
    }
    
    private function extractValue(string $arg): string
    {
        // Extract value from --option=value format
        if (str_contains($arg, '=')) {
            return substr($arg, strpos($arg, '=') + 1);
        }
        return $arg;
    }
    
    private function getOptionCompletions(string $command, string $partial): array
    {
        $options = $this->getCommandOptions($command);
        
        if (empty($partial) || $partial === '--') {
            return array_map(fn($opt) => '--' . $opt, $options);
        }
        
        $matches = array_filter($options, function($opt) use ($partial) {
            return str_starts_with('--' . $opt, $partial);
        });
        
        return array_map(fn($opt) => '--' . $opt, array_values($matches));
    }
    
    private function getCommandOptions(string $command): array
    {
        return match($command) {
            'set' => ['stage', 'vault', 'secure', 'force'],
            'get' => ['stage', 'vault', 'format'],
            'delete' => ['stage', 'vault', 'force'],
            'show' => ['stage', 'vault', 'unmask', 'format', 'only', 'except'],
            'copy' => ['from', 'to', 'overwrite', 'dry-run', 'only', 'except'],
            'import' => ['stage', 'vault', 'skip-existing', 'overwrite', 'dry-run', 'only', 'except'],
            'export' => ['stage', 'vault', 'format', 'template', 'all', 'missing', 'file', 'append', 'overwrite', 'only', 'except'],
            'diff' => ['vault', 'stage', 'unmask', 'only', 'except'],
            default => []
        };
    }
}
<?php

namespace STS\Keep\Shell;

use Psy\Shell;
use STS\Keep\Shell\Commands\ContextCommand;
use STS\Keep\Shell\Commands\HelpCommand;
use STS\Keep\Shell\Commands\KeepProxyCommand;
use STS\Keep\Shell\Commands\ListCommand;
use STS\Keep\Shell\Commands\StageCommand;
use STS\Keep\Shell\Commands\UseCommand;
use STS\Keep\Shell\Commands\VaultCommand;
use STS\Keep\Shell\Completers;

class KeepShell extends Shell
{
    private ShellContext $context;
    private CommandExecutor $executor;
    
    public function __construct(ShellContext $context, CommandExecutor $executor)
    {
        $config = new KeepShellConfiguration($context);
        parent::__construct($config);
        
        $this->context = $context;
        $this->executor = $executor;
        
        // Add our custom shell commands
        $this->add(new StageCommand($context));
        $this->add(new VaultCommand($context));
        $this->add(new UseCommand($context));
        $this->add(new ContextCommand($context));
        
        // Register Keep commands as PsySH commands
        $this->registerKeepCommands();
        
        // Override the help and list commands with our custom versions
        $this->add(new HelpCommand());
        $this->add(new ListCommand());
        
        // Pre-load secrets to ensure they're available for tab completion
        $this->preloadSecrets($context);
    }
    
    private function preloadSecrets(ShellContext $context): void
    {
        $context->getCachedSecretNames();
    }
    
    /**
     * Override to only include essential PsySH commands
     */
    protected function getDefaultCommands(): array
    {
        return [
            new \Psy\Command\ExitCommand(),
            new \Psy\Command\ClearCommand(),
        ];
    }
    
    /**
     * Override to exclude default matchers that interfere with our completions
     */
    protected function getDefaultMatchers(): array
    {
        // Only return our custom matcher, no PsySH defaults
        return [
            new KeepCommandMatcher(
                new Completers\CommandCompleter(),
                new Completers\SecretCompleter($this->context),
                new Completers\StageCompleter($this->context),
                new Completers\VaultCompleter($this->context)
            ),
        ];
    }
    
    /**
     * Override to check for invalid command attempts and show helpful messages
     */
    protected function hasCommand(string $input): bool
    {
        // First check if it's a valid command
        if (parent::hasCommand($input)) {
            return true;
        }
        
        // Extract what looks like the command name
        if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_:]*)(?:\s|$)/', trim($input), $matches)) {
            $command = $matches[1];
            
            // Skip PHP keywords and variables
            if ($this->isPhpKeyword($command) || str_starts_with($command, '$')) {
                return false;
            }
            
            // Check if this looks like a command attempt
            // If it contains colons or underscores, it's likely a command attempt
            if (str_contains($command, ':') || str_contains($command, '_')) {
                return true; // Return true to prevent PHP evaluation
            }
            
            // For single words, only show error if it's similar to a known command
            $suggestions = $this->getSuggestions($command);
            if (!empty($suggestions)) {
                return true; // Return true to prevent PHP evaluation
            }
        }
        
        return false;
    }
    
    /**
     * Override to provide better error messages for invalid commands
     */
    protected function runCommand(string $input)
    {
        try {
            return parent::runCommand($input);
        } catch (\InvalidArgumentException $e) {
            // Extract the command name from the input
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_:]*)(?:\s|$)/', trim($input), $matches)) {
                $command = $matches[1];
                $suggestions = $this->getSuggestions($command);
                $this->showInvalidCommandError($command, $suggestions);
                return;
            }
            throw $e;
        }
    }
    
    /**
     * Show an error message for invalid commands
     */
    private function showInvalidCommandError(string $command, array $suggestions = []): void
    {
        echo "\033[31mCommand \"$command\" not found.\033[0m";
        
        if (!empty($suggestions)) {
            echo " Did you mean: " . implode(', ', $suggestions) . "?";
        } else {
            echo " Type \"help\" to see available commands.";
        }
        
        echo "\n";
    }
    
    /**
     * Check if a word is a PHP keyword
     */
    private function isPhpKeyword(string $word): bool
    {
        $keywords = [
            'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch',
            'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do',
            'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach',
            'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'final',
            'finally', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if',
            'implements', 'include', 'include_once', 'instanceof', 'insteadof',
            'interface', 'isset', 'list', 'match', 'namespace', 'new', 'or', 'print',
            'private', 'protected', 'public', 'readonly', 'require', 'require_once',
            'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use',
            'var', 'while', 'xor', 'yield', 'true', 'false', 'null'
        ];
        
        return in_array(strtolower($word), $keywords);
    }
    
    /**
     * Get command suggestions for a misspelled command
     */
    private function getSuggestions(string $input): array
    {
        $commands = [
            'get', 'set', 'delete', 'show', 'copy', 'export', 'diff', 
            'verify', 'info', 'history', 'stage', 'vault', 'use', 
            'context', 'help', 'exit', 'clear'
        ];
        
        $suggestions = [];
        $input = strtolower($input);
        
        foreach ($commands as $command) {
            // Check for commands that start with the input
            if (str_starts_with($command, $input)) {
                $suggestions[] = $command;
                continue;
            }
            
            // Check for similar commands (Levenshtein distance)
            if (levenshtein($input, $command) <= 2) {
                $suggestions[] = $command;
            }
        }
        
        return array_unique($suggestions);
    }
    
    private function registerKeepCommands(): void
    {
        // Define Keep commands and their aliases
        // Note: Configuration commands (configure, vault:add, stage:add, etc.) 
        // are intentionally excluded from the shell - use the standalone CLI for those
        $commands = [
            'get' => ['g'],
            'set' => ['s'],
            'delete' => ['d'],
            'show' => ['l', 'ls'],
            'copy' => [],
            'export' => [],
            'diff' => [],
            'verify' => [],
            'info' => [],
            'history' => [],
        ];
        
        foreach ($commands as $command => $aliases) {
            $this->add(new KeepProxyCommand($this->executor, $command, $aliases));
        }
    }
    
}
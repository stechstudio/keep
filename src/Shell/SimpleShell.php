<?php

namespace STS\Keep\Shell;

use Illuminate\Console\Application;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class SimpleShell
{
    private ShellContext $context;
    private CommandExecutor $executor;
    private ConsoleOutput $output;
    private Application $application;
    private string $historyFile;
    private array $commands = [
        'get', 'g', 'set', 's', 'delete', 'd', 'show', 'l', 'ls',
        'copy', 'export', 'diff', 'verify', 'info', 'history',
        'stage', 'vault', 'use', 'u', 'context', 'ctx',
        'help', '?', 'clear', 'cls', 'exit', 'quit', 'q'
    ];
    
    public function __construct(ShellContext $context, Application $application)
    {
        $this->context = $context;
        $this->application = $application;
        $this->executor = new CommandExecutor($context, $application);
        $this->output = new ConsoleOutput();
        
        // Set up history file
        $this->historyFile = $_SERVER['HOME'] . '/.keep_history';
        
        // Configure output styles
        $this->configureOutputStyles();
        
        // Set up readline completion
        readline_completion_function([$this, 'completer']);
        
        // Load history
        if (file_exists($this->historyFile)) {
            readline_read_history($this->historyFile);
        }
    }
    
    private function configureOutputStyles(): void
    {
        $formatter = $this->output->getFormatter();
        $formatter->setStyle('info', new OutputFormatterStyle('green'));
        $formatter->setStyle('comment', new OutputFormatterStyle('yellow'));
        $formatter->setStyle('error', new OutputFormatterStyle('red'));
        $formatter->setStyle('warning', new OutputFormatterStyle('yellow'));
    }
    
    public function run(): void
    {
        $this->showWelcomeMessage();
        
        while (true) {
            $prompt = $this->getPrompt();
            $input = readline($prompt);
            
            // Handle Ctrl+D
            if ($input === false) {
                $this->output->writeln('');
                break;
            }
            
            $input = trim($input);
            
            // Skip empty input
            if (empty($input)) {
                continue;
            }
            
            // Add to history
            readline_add_history($input);
            readline_write_history($this->historyFile);
            
            // Handle built-in commands
            if ($this->handleBuiltInCommand($input)) {
                continue;
            }
            
            // Execute Keep command
            $this->executeCommand($input);
        }
        
        $this->output->writeln('Goodbye!');
    }
    
    private function showWelcomeMessage(): void
    {
        $this->output->writeln('');
        $this->output->writeln('<info>Welcome to Keep Shell v1.0.0</info>');
        $this->output->writeln("Type 'help' for available commands or 'exit' to quit.");
        $this->output->writeln(sprintf(
            'Current context: <comment>%s:%s</comment>',
            $this->context->getVault(),
            $this->context->getStage()
        ));
        $this->output->writeln('<comment>Tab completion is available for commands and secret names!</comment>');
        $this->output->writeln('');
    }
    
    private function getPrompt(): string
    {
        return sprintf(
            "\033[32m%s:%s\033[0m> ",
            $this->context->getVault(),
            $this->context->getStage()
        );
    }
    
    private function handleBuiltInCommand(string $input): bool
    {
        $parts = explode(' ', $input);
        $command = $parts[0];
        
        switch ($command) {
            case 'exit':
            case 'quit':
            case 'q':
                $this->output->writeln('Goodbye!');
                exit(0);
                
            case 'clear':
            case 'cls':
                // Clear screen
                system('clear');
                return true;
                
            case 'help':
            case '?':
                $this->showHelp();
                return true;
                
            case 'context':
            case 'ctx':
                $this->showContext();
                return true;
                
            case 'stage':
            case 's':
                if (isset($parts[1])) {
                    $this->switchStage($parts[1]);
                } else {
                    $this->listStages();
                }
                return true;
                
            case 'vault':
            case 'v':
                if (isset($parts[1])) {
                    $this->switchVault($parts[1]);
                } else {
                    $this->listVaults();
                }
                return true;
                
            case 'use':
            case 'u':
                if (isset($parts[1])) {
                    $this->switchContext($parts[1]);
                } else {
                    $this->output->writeln('<error>Usage: use <vault:stage></error>');
                }
                return true;
        }
        
        return false;
    }
    
    private function executeCommand(string $input): void
    {
        // Check if it looks like a valid command
        $parts = explode(' ', $input);
        $command = $parts[0];
        
        // Map shortcuts
        $command = match($command) {
            'g' => 'get',
            's' => 'set',
            'd' => 'delete',
            'l', 'ls' => 'show',
            default => $command
        };
        
        // Check if it's a known Keep command
        $knownCommands = ['get', 'set', 'delete', 'show', 'copy', 'export', 
                         'diff', 'verify', 'info', 'history'];
        
        if (!in_array($command, $knownCommands)) {
            // Check for suggestions
            $suggestions = $this->getSuggestions($command);
            
            $message = sprintf('Command "%s" not found.', $command);
            if (!empty($suggestions)) {
                $message .= sprintf(' Did you mean: %s?', implode(', ', $suggestions));
            } else {
                $message .= ' Type "help" to see available commands.';
            }
            
            $this->output->writeln("<error>$message</error>");
            return;
        }
        
        try {
            $exitCode = $this->executor->execute($input);
            
            if ($exitCode !== 0 && $exitCode !== null) {
                $this->output->writeln('<error>Command failed.</error>');
            }
        } catch (\Exception $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }
    
    private function showHelp(): void
    {
        $this->output->writeln('');
        $this->output->writeln('<info>Keep Shell Commands</info>');
        $this->output->writeln('');
        
        $commands = [
            '<comment>Secret Management</comment>' => [
                'get <key>' => 'Get a secret value (alias: g)',
                'set <key> <value>' => 'Set a secret (alias: s)',
                'delete <key>' => 'Delete a secret (alias: d)',
                'show' => 'Show all secrets (aliases: l, ls)',
                'history <key>' => 'View secret history',
                'copy <key>' => 'Copy single secret',
                'copy only <pattern>' => 'Copy secrets matching pattern',
                'diff <stage1> <stage2>' => 'Compare secrets between stages',
            ],
            '<comment>Context Management</comment>' => [
                'stage <name>' => 'Switch to a different stage',
                'vault <name>' => 'Switch to a different vault',
                'use <vault:stage>' => 'Switch both vault and stage (alias: u)',
                'context' => 'Show current context (alias: ctx)',
            ],
            '<comment>Analysis & Export</comment>' => [
                'export' => 'Export secrets to .env format',
                'verify' => 'Verify template placeholders',
                'info' => 'Show vault information',
            ],
            '<comment>Other</comment>' => [
                'exit' => 'Exit the shell (or Ctrl+D)',
                'help' => 'Show this help message (alias: ?)',
                'clear' => 'Clear the screen (alias: cls)',
            ],
        ];
        
        foreach ($commands as $section => $sectionCommands) {
            $this->output->writeln($section);
            
            $maxLen = max(array_map('strlen', array_keys($sectionCommands)));
            
            foreach ($sectionCommands as $cmd => $description) {
                $this->output->writeln(sprintf(
                    '  <info>%-' . ($maxLen + 2) . 's</info> %s',
                    $cmd,
                    $description
                ));
            }
            
            $this->output->writeln('');
        }
    }
    
    private function showContext(): void
    {
        $this->output->writeln(sprintf(
            'Current context: <info>%s:%s</info>',
            $this->context->getVault(),
            $this->context->getStage()
        ));
    }
    
    private function switchStage(string $stage): void
    {
        $this->context->setStage($stage);
        $this->output->writeln(sprintf(
            'Switched to stage: <info>%s</info>',
            $stage
        ));
    }
    
    private function switchVault(string $vault): void
    {
        $this->context->setVault($vault);
        $this->output->writeln(sprintf(
            'Switched to vault: <info>%s</info>',
            $vault
        ));
    }
    
    private function switchContext(string $context): void
    {
        if (!str_contains($context, ':')) {
            $this->output->writeln('<error>Format must be vault:stage</error>');
            return;
        }
        
        [$vault, $stage] = explode(':', $context, 2);
        $this->context->setVault($vault);
        $this->context->setStage($stage);
        
        $this->output->writeln(sprintf(
            'Switched to: <info>%s:%s</info>',
            $vault,
            $stage
        ));
    }
    
    private function listStages(): void
    {
        $stages = $this->context->getAvailableStages();
        $current = $this->context->getStage();
        
        $this->output->writeln('Available stages:');
        foreach ($stages as $stage) {
            if ($stage === $current) {
                $this->output->writeln("  <info>* $stage</info> (current)");
            } else {
                $this->output->writeln("    $stage");
            }
        }
    }
    
    private function listVaults(): void
    {
        $vaults = $this->context->getAvailableVaults();
        $current = $this->context->getVault();
        
        $this->output->writeln('Available vaults:');
        foreach ($vaults as $vault) {
            if ($vault === $current) {
                $this->output->writeln("  <info>* $vault</info> (current)");
            } else {
                $this->output->writeln("    $vault");
            }
        }
    }
    
    /**
     * Readline tab completion callback
     */
    public function completer(string $input, int $index): array
    {
        $info = readline_info();
        $line = substr($info['line_buffer'], 0, $info['end']);
        $parts = explode(' ', $line);
        
        // If we're completing the first word, return commands
        if (count($parts) === 1) {
            return $this->getCommandCompletions($parts[0]);
        }
        
        // Otherwise, complete based on the command
        $command = $parts[0];
        $currentArg = end($parts);
        
        // Map shortcuts
        $command = match($command) {
            'g' => 'get',
            's' => 'set',
            'd' => 'delete',
            'l', 'ls' => 'show',
            'v' => 'vault',
            'u' => 'use',
            default => $command
        };
        
        // Complete based on command type
        switch ($command) {
            case 'get':
            case 'set':
            case 'delete':
            case 'history':
                return $this->getSecretCompletions($currentArg);
                
            case 'stage':
                return $this->getStageCompletions($currentArg);
                
            case 'vault':
                return $this->getVaultCompletions($currentArg);
                
            case 'use':
                return $this->getContextCompletions($currentArg);
                
            case 'diff':
                // Complete stages
                return $this->getStageCompletions($currentArg);
                
            case 'show':
                // If typing "only" or "except", don't complete
                if (in_array($currentArg, ['only', 'except'])) {
                    return [];
                }
                // Otherwise might be typing those keywords
                if (str_starts_with('only', $currentArg)) {
                    return ['only'];
                }
                if (str_starts_with('except', $currentArg)) {
                    return ['except'];
                }
                return [];
                
            default:
                return [];
        }
    }
    
    private function getCommandCompletions(string $prefix): array
    {
        if (empty($prefix)) {
            return $this->commands;
        }
        
        return array_filter($this->commands, function($cmd) use ($prefix) {
            return str_starts_with($cmd, $prefix);
        });
    }
    
    private function getSecretCompletions(string $prefix): array
    {
        $secrets = $this->context->getCachedSecretNames();
        
        if (empty($prefix)) {
            return $secrets;
        }
        
        return array_filter($secrets, function($secret) use ($prefix) {
            return str_starts_with($secret, $prefix);
        });
    }
    
    private function getStageCompletions(string $prefix): array
    {
        $stages = $this->context->getAvailableStages();
        
        if (empty($prefix)) {
            return $stages;
        }
        
        return array_filter($stages, function($stage) use ($prefix) {
            return str_starts_with($stage, $prefix);
        });
    }
    
    private function getVaultCompletions(string $prefix): array
    {
        $vaults = $this->context->getAvailableVaults();
        
        if (empty($prefix)) {
            return $vaults;
        }
        
        return array_filter($vaults, function($vault) use ($prefix) {
            return str_starts_with($vault, $prefix);
        });
    }
    
    private function getContextCompletions(string $prefix): array
    {
        $contexts = [];
        $vaults = $this->context->getAvailableVaults();
        $stages = $this->context->getAvailableStages();
        
        foreach ($vaults as $vault) {
            foreach ($stages as $stage) {
                $contexts[] = "$vault:$stage";
            }
        }
        
        if (empty($prefix)) {
            return $contexts;
        }
        
        return array_filter($contexts, function($ctx) use ($prefix) {
            return str_starts_with($ctx, $prefix);
        });
    }
    
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
}
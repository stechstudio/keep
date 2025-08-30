<?php

namespace STS\Keep\Commands;

use Psy\Shell;
use STS\Keep\Shell\CommandExecutor;
use STS\Keep\Shell\KeepShell;
use STS\Keep\Shell\ShellContext;

class ShellCommand extends BaseCommand
{
    protected $signature = 'shell 
        {--stage= : Initial stage to use}
        {--vault= : Initial vault to use}
        {--simple : Use simple shell without PsySH (fallback mode)}';
    
    protected $description = 'Start an interactive shell for Keep commands';
    
    public function process()
    {
        // Check if we should use simple mode
        if ($this->option('simple') || !class_exists(Shell::class)) {
            return $this->runSimpleShell();
        }
        
        try {
            return $this->runPsyShell();
        } catch (\Exception $e) {
            $this->warn('PsySH shell failed to start, falling back to simple mode.');
            $this->error($e->getMessage());
            return $this->runSimpleShell();
        }
    }
    
    /**
     * Run the full PsySH-powered shell with tab completion
     */
    private function runPsyShell(): int
    {
        // Create context with initial settings
        $context = new ShellContext(
            $this->option('stage'),
            $this->option('vault')
        );
        
        // Create command executor
        $executor = new CommandExecutor($context, $this->getApplication());
        
        // Create and run the shell
        $shell = new KeepShell($context, $executor);
        
        try {
            $shell->run();
        } catch (\Exception $e) {
            // Handle exit gracefully
            if (!str_contains($e->getMessage(), 'Exit')) {
                throw $e;
            }
        }
        
        $this->info('Goodbye!');
        return self::SUCCESS;
    }
    
    /**
     * Fallback to simple shell mode (original implementation)
     */
    private function runSimpleShell(): int
    {
        $context = new ShellContext(
            $this->option('stage'),
            $this->option('vault')
        );
        
        $this->showSimpleWelcome($context);
        
        $running = true;
        while ($running) {
            $input = $this->simplePrompt($context);
            
            if ($input === null || $input === '') {
                continue;
            }
            
            $context->addToHistory($input);
            
            if ($this->handleSimpleCommand($input, $context, $running)) {
                continue;
            }
            
            // Execute as Keep command
            $executor = new CommandExecutor($context, $this->getApplication());
            $executor->execute($input);
        }
        
        $this->info('Goodbye!');
        return self::SUCCESS;
    }
    
    private function showSimpleWelcome(ShellContext $context): void
    {
        $this->newLine();
        $this->info('Welcome to Keep Shell v1.0.0 (Simple Mode)');
        $this->line('Type \'help\' for available commands or \'exit\' to quit.');
        $this->line("Current context: {$context->getVault()}:{$context->getStage()}");
        $this->warn('Note: Tab completion is not available in simple mode. Use --simple=false to try PsySH mode.');
        $this->newLine();
    }
    
    private function simplePrompt(ShellContext $context): ?string
    {
        // Check if we're in an interactive terminal
        if (!stream_isatty(STDIN)) {
            $line = fgets(STDIN);
            return $line !== false ? trim($line) : null;
        }
        
        // Interactive mode - use readline if available
        if (function_exists('readline')) {
            $prompt = $context->getPrompt();
            $line = readline($prompt);
            if ($line !== false && $line !== '') {
                readline_add_history($line);
            }
            return $line;
        }
        
        // Fallback to basic input
        echo $context->getPrompt();
        $line = fgets(STDIN);
        return $line !== false ? trim($line) : null;
    }
    
    private function handleSimpleCommand(string $input, ShellContext $context, bool &$running): bool
    {
        $parts = explode(' ', $input);
        $command = $parts[0];
        $arg = $parts[1] ?? null;
        
        switch ($command) {
            case 'exit':
            case 'quit':
            case 'q':
                $running = false;
                return true;
                
            case 'help':
            case '?':
                $this->showSimpleHelp();
                return true;
                
            case 'clear':
            case 'cls':
                system('clear');
                return true;
                
            case 'stage':
            case 's':
                if ($arg) {
                    $this->switchStage($arg, $context);
                } else {
                    $this->info("Current stage: {$context->getStage()}");
                }
                return true;
                
            case 'vault':
            case 'v':
                if ($arg) {
                    $this->switchVault($arg, $context);
                } else {
                    $this->info("Current vault: {$context->getVault()}");
                }
                return true;
                
            case 'context':
            case 'ctx':
                $this->showContext($context);
                return true;
                
            case 'use':
            case 'u':
                if ($arg && str_contains($arg, ':')) {
                    [$vault, $stage] = explode(':', $arg);
                    $this->switchVault($vault, $context);
                    $this->switchStage($stage, $context);
                } else {
                    $this->error('Usage: use <vault>:<stage>');
                }
                return true;
                
            case 'history':
            case 'h':
                $this->showHistory($context);
                return true;
                
            case 'ls':
            case 'list':
                // These will be handled as Keep commands
                return false;
        }
        
        return false;
    }
    
    private function switchStage(string $stage, ShellContext $context): void
    {
        $stages = $context->getAvailableStages();
        
        // Allow partial matching
        $matched = null;
        foreach ($stages as $s) {
            if ($s === $stage || str_starts_with($s, $stage)) {
                $matched = $s;
                break;
            }
        }
        
        if ($matched) {
            $context->setStage($matched);
            $this->info("✓ Switched to stage: {$matched}");
        } else {
            $this->error("Unknown stage: {$stage}");
            $this->line("Available stages: " . implode(', ', $stages));
        }
    }
    
    private function switchVault(string $vault, ShellContext $context): void
    {
        $vaults = $context->getAvailableVaults();
        
        // Allow partial matching
        $matched = null;
        foreach ($vaults as $v) {
            if ($v === $vault || str_starts_with($v, $vault)) {
                $matched = $v;
                break;
            }
        }
        
        if ($matched) {
            $context->setVault($matched);
            $this->info("✓ Switched to vault: {$matched}");
        } else {
            $this->error("Unknown vault: {$vault}");
            $this->line("Available vaults: " . implode(', ', $vaults));
        }
    }
    
    private function showContext(ShellContext $context): void
    {
        $this->line('Current Context:');
        $this->line("  Vault: {$context->getVault()}");
        $this->line("  Stage: {$context->getStage()}");
        $this->line("  Available stages: " . implode(', ', $context->getAvailableStages()));
        $this->line("  Available vaults: " . implode(', ', $context->getAvailableVaults()));
    }
    
    private function showHistory(ShellContext $context): void
    {
        $history = $context->getHistory();
        
        if (empty($history)) {
            $this->info('No command history yet.');
            return;
        }
        
        $this->line('Command History:');
        foreach ($history as $i => $cmd) {
            $this->line(sprintf("  %3d  %s", $i + 1, $cmd));
        }
    }
    
    private function showSimpleHelp(): void
    {
        $this->newLine();
        $this->info('Keep Shell Commands:');
        $this->newLine();
        
        $this->line('Context Management:');
        $this->line('  stage <name>     Switch to a different stage (alias: s)');
        $this->line('  vault <name>     Switch to a different vault (alias: v)');
        $this->line('  use <v:s>        Switch both vault and stage (alias: u)');
        $this->line('  context          Show current context (alias: ctx)');
        $this->newLine();
        
        $this->line('Secret Operations:');
        $this->line('  set <key> [val]  Set a secret value');
        $this->line('  get <key>        Get a secret value (alias: g)');
        $this->line('  delete <key>     Delete a secret (alias: d)');
        $this->line('  copy <key>       Copy a secret');
        $this->line('  show             List all secrets (aliases: ls, list, l)');
        $this->newLine();
        
        $this->line('Shell Commands:');
        $this->line('  clear            Clear the screen (alias: cls)');
        $this->line('  history          Show command history (alias: h)');
        $this->line('  help             Show this help (alias: ?)');
        $this->line('  exit             Exit the shell (aliases: quit, q)');
        $this->newLine();
        
        $this->line('Tips:');
        $this->line('  - Commands use current context (no need for --stage/--vault)');
        $this->line('  - Use partial names for stages/vaults (e.g., "s prod" for "stage production")');
        $this->line('  - All standard Keep commands are available');
        $this->newLine();
    }
}
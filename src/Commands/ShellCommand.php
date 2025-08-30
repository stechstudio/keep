<?php

namespace STS\Keep\Commands;

use Illuminate\Console\Application;
use STS\Keep\Data\Settings;
use STS\Keep\Facades\Keep;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

use function Laravel\Prompts\text;
use function Laravel\Prompts\info;

class ShellCommand extends BaseCommand
{
    protected $signature = 'shell 
        {--stage= : Initial stage to use}
        {--vault= : Initial vault to use}';
    
    protected $description = 'Start an interactive shell for Keep commands';
    
    private string $currentStage;
    private string $currentVault;
    private array $history = [];
    private bool $running = true;
    
    public function process()
    {
        $this->initializeContext();
        $this->showWelcome();
        
        while ($this->running) {
            $input = $this->prompt();
            
            if ($input === null || $input === '') {
                continue;
            }
            
            $this->history[] = $input;
            $this->executeCommand($input);
        }
        
        $this->info('Goodbye!');
        return self::SUCCESS;
    }
    
    private function initializeContext(): void
    {
        $settings = Settings::load();
        
        // Set initial stage
        $this->currentStage = $this->option('stage') 
            ?? $settings->stages()[0] 
            ?? 'development';
            
        // Set initial vault
        $this->currentVault = $this->option('vault') 
            ?? $settings->defaultVault() 
            ?? 'test';
    }
    
    private function showWelcome(): void
    {
        $this->newLine();
        $this->info('Welcome to Keep Shell v1.0.0');
        $this->line('Type \'help\' for available commands or \'exit\' to quit.');
        $this->line("Current context: {$this->currentVault}:{$this->currentStage}");
        $this->newLine();
    }
    
    private function prompt(): ?string
    {
        // Check if we're in an interactive terminal
        if (!stream_isatty(STDIN)) {
            // Non-interactive mode - read from stdin
            $line = fgets(STDIN);
            return $line !== false ? trim($line) : null;
        }
        
        // Interactive mode - use readline if available
        if (function_exists('readline')) {
            $prompt = "keep ({$this->currentVault}:{$this->currentStage})> ";
            $line = readline($prompt);
            if ($line !== false && $line !== '') {
                readline_add_history($line);
            }
            return $line;
        }
        
        // Fallback to basic input
        echo "keep ({$this->currentVault}:{$this->currentStage})> ";
        $line = fgets(STDIN);
        return $line !== false ? trim($line) : null;
    }
    
    private function executeCommand(string $input): void
    {
        $input = trim($input);
        
        // Handle shell-specific commands
        if ($this->handleShellCommand($input)) {
            return;
        }
        
        // Parse the command
        $parts = str_getcsv($input, ' ');
        $command = array_shift($parts);
        
        // Map common shortcuts
        $command = $this->mapShortcut($command);
        
        // Execute Keep command
        $this->executeKeepCommand($command, $parts);
    }
    
    private function handleShellCommand(string $input): bool
    {
        $parts = explode(' ', $input);
        $command = $parts[0];
        $arg = $parts[1] ?? null;
        
        switch ($command) {
            case 'exit':
            case 'quit':
            case 'q':
                $this->running = false;
                return true;
                
            case 'help':
            case '?':
                $this->showHelp();
                return true;
                
            case 'clear':
            case 'cls':
                system('clear');
                return true;
                
            case 'stage':
            case 's':
                if ($arg) {
                    $this->switchStage($arg);
                } else {
                    $this->info("Current stage: {$this->currentStage}");
                }
                return true;
                
            case 'vault':
            case 'v':
                if ($arg) {
                    $this->switchVault($arg);
                } else {
                    $this->info("Current vault: {$this->currentVault}");
                }
                return true;
                
            case 'context':
            case 'ctx':
                $this->showContext();
                return true;
                
            case 'use':
            case 'u':
                if ($arg && str_contains($arg, ':')) {
                    [$vault, $stage] = explode(':', $arg);
                    $this->switchVault($vault);
                    $this->switchStage($stage);
                } else {
                    $this->error('Usage: use <vault>:<stage>');
                }
                return true;
                
            case 'history':
            case 'h':
                $this->showHistory();
                return true;
                
            case 'ls':
            case 'list':
                $this->executeKeepCommand('show', []);
                return true;
        }
        
        return false;
    }
    
    private function mapShortcut(string $command): string
    {
        return match($command) {
            'g' => 'get',
            'd' => 'delete',
            'l' => 'show',
            default => $command
        };
    }
    
    private function executeKeepCommand(string $command, array $args): void
    {
        try {
            // Build the full command with context
            $fullCommand = $this->buildFullCommand($command, $args);
            
            // Get the application instance
            $app = $this->getApplication();
            
            // Find the command
            $commandName = $this->resolveCommandName($command);
            if (!$app->has($commandName)) {
                $this->error("Unknown command: {$command}");
                return;
            }
            
            // Create input/output
            $input = new ArrayInput($fullCommand);
            $output = new ConsoleOutput();
            
            // Run the command
            $exitCode = $app->find($commandName)->run($input, $output);
            
            if ($exitCode !== 0 && $exitCode !== null) {
                $this->error("Command failed with exit code: {$exitCode}");
            }
            
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
    
    private function buildFullCommand(string $command, array $args): array
    {
        $fullCommand = ['command' => $this->resolveCommandName($command)];
        
        // Add context unless already specified
        $needsStage = !in_array($command, ['configure', 'vault:add', 'vault:list', 'stage:add']);
        $needsVault = !in_array($command, ['configure', 'vault:add', 'vault:list', 'stage:add', 'import', 'export']);
        
        // Parse arguments and options
        $positionals = [];
        $options = [];
        
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                if (str_contains($arg, '=')) {
                    [$key, $value] = explode('=', $arg, 2);
                    $options[substr($key, 2)] = $value;
                } else {
                    $options[substr($arg, 2)] = true;
                }
            } else {
                $positionals[] = $arg;
            }
        }
        
        // Add positional arguments
        if ($command === 'set' && count($positionals) >= 1) {
            $fullCommand['key'] = $positionals[0];
            if (isset($positionals[1])) {
                $fullCommand['value'] = $positionals[1];
            }
        } elseif (in_array($command, ['get', 'delete']) && count($positionals) >= 1) {
            $fullCommand['key'] = $positionals[0];
        } elseif ($command === 'copy' && count($positionals) >= 1) {
            $fullCommand['key'] = $positionals[0];
        } elseif ($command === 'import' && count($positionals) >= 1) {
            $fullCommand['file'] = $positionals[0];
        } elseif ($command === 'stage:add' && count($positionals) >= 1) {
            $fullCommand['name'] = $positionals[0];
        }
        
        // Add options
        foreach ($options as $key => $value) {
            $fullCommand['--' . $key] = $value;
        }
        
        // Add context if not specified
        if ($needsStage && !isset($options['stage'])) {
            $fullCommand['--stage'] = $this->currentStage;
        }
        if ($needsVault && !isset($options['vault'])) {
            $fullCommand['--vault'] = $this->currentVault;
        }
        
        // Special handling for copy command
        if ($command === 'copy' && !isset($options['from'])) {
            $fullCommand['--from'] = "{$this->currentVault}:{$this->currentStage}";
        }
        
        return $fullCommand;
    }
    
    private function resolveCommandName(string $command): string
    {
        // Handle commands with colons
        if (str_contains($command, ':')) {
            return $command;
        }
        
        // Map to actual command names
        return match($command) {
            'configure' => 'configure',
            'set' => 'set',
            'get' => 'get',
            'show' => 'show',
            'delete' => 'delete',
            'copy' => 'copy',
            'import' => 'import',
            'export' => 'export',
            'diff' => 'diff',
            'verify' => 'verify',
            'info' => 'info',
            default => $command
        };
    }
    
    private function switchStage(string $stage): void
    {
        $settings = Settings::load();
        $stages = $settings->stages();
        
        // Allow partial matching
        $matched = null;
        foreach ($stages as $s) {
            if ($s === $stage || str_starts_with($s, $stage)) {
                $matched = $s;
                break;
            }
        }
        
        if ($matched) {
            $this->currentStage = $matched;
            $this->info("✓ Switched to stage: {$this->currentStage}");
        } else {
            $this->error("Unknown stage: {$stage}");
            $this->line("Available stages: " . implode(', ', $stages));
        }
    }
    
    private function switchVault(string $vault): void
    {
        $vaults = Keep::manager()->listVaults();
        $vaultNames = $vaults->pluck('slug')->toArray();
        
        // Allow partial matching
        $matched = null;
        foreach ($vaultNames as $v) {
            if ($v === $vault || str_starts_with($v, $vault)) {
                $matched = $v;
                break;
            }
        }
        
        if ($matched) {
            $this->currentVault = $matched;
            $this->info("✓ Switched to vault: {$this->currentVault}");
        } else {
            $this->error("Unknown vault: {$vault}");
            $this->line("Available vaults: " . implode(', ', $vaultNames));
        }
    }
    
    private function showContext(): void
    {
        $settings = Settings::load();
        
        $this->line('Current Context:');
        $this->line("  Vault: {$this->currentVault}");
        $this->line("  Stage: {$this->currentStage}");
        $this->line("  Default vault: " . $settings->defaultVault());
        $this->line("  Available stages: " . implode(', ', $settings->stages()));
    }
    
    private function showHistory(): void
    {
        if (empty($this->history)) {
            $this->info('No command history yet.');
            return;
        }
        
        $this->line('Command History:');
        foreach ($this->history as $i => $cmd) {
            $this->line(sprintf("  %3d  %s", $i + 1, $cmd));
        }
    }
    
    private function showHelp(): void
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
<?php

namespace STS\Keep\Shell\Commands;

use Closure;
use STS\Keep\Shell\ShellContext;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\select;

class BuiltInCommands
{
    protected array $commands = [];
    
    public function __construct(
        private ShellContext $context,
        private OutputInterface $output
    ) {
        $this->registerCommands();
    }
    
    protected function registerCommands(): void
    {
        $this->register(['exit', 'quit', 'q'], fn() => $this->exit());
        $this->register(['clear', 'cls'], fn() => $this->clear());
        $this->register(['help', '?'], fn() => $this->help());
        $this->register(['context', 'ctx'], fn() => $this->context());
        $this->register('stage', fn($args) => $this->stage($args));
        $this->register('vault', fn($args) => $this->vault($args));
        $this->register(['use', 'u'], fn($args) => $this->use($args));
    }
    
    protected function register(string|array $commands, Closure $handler): void
    {
        foreach ((array) $commands as $command) {
            $this->commands[$command] = $handler;
        }
    }
    
    public function has(string $command): bool
    {
        return isset($this->commands[$command]);
    }
    
    public function handle(string $command, array $args = []): bool
    {
        if (!$this->has($command)) {
            return false;
        }
        
        $this->commands[$command]($args);
        return true;
    }
    
    protected function exit(): void
    {
        $this->output->writeln('Goodbye!');
        exit(0);
    }
    
    protected function clear(): void
    {
        system('clear');
    }
    
    protected function help(): void
    {
        $sections = $this->getHelpSections();
        
        $this->output->writeln('');
        $this->output->writeln('<info>Keep Shell Commands</info>');
        $this->output->writeln('');
        
        foreach ($sections as $title => $commands) {
            $this->output->writeln($title);
            
            $maxLength = max(array_map('strlen', array_keys($commands)));
            
            foreach ($commands as $command => $description) {
                $this->output->writeln(sprintf(
                    '  <info>%-' . ($maxLength + 2) . 's</info> %s',
                    $command,
                    $description
                ));
            }
            
            $this->output->writeln('');
        }
    }
    
    protected function context(): void
    {
        $this->output->writeln(sprintf(
            'Current context: <info>%s:%s</info>',
            $this->context->getVault(),
            $this->context->getStage()
        ));
    }
    
    protected function stage(array $args): void
    {
        if (isset($args[0])) {
            $this->switchStage($args[0]);
            return;
        }
        
        $this->selectStage();
    }
    
    protected function vault(array $args): void
    {
        if (isset($args[0])) {
            $this->switchVault($args[0]);
            return;
        }
        
        $this->selectVault();
    }
    
    protected function use(array $args): void
    {
        if (!isset($args[0])) {
            $this->output->writeln('<error>Usage: use <vault:stage></error>');
            return;
        }
        
        if (!str_contains($args[0], ':')) {
            $this->output->writeln('<error>Format must be vault:stage</error>');
            return;
        }
        
        [$vault, $stage] = explode(':', $args[0], 2);
        
        $this->context->setVault($vault);
        $this->context->setStage($stage);
        
        $this->output->writeln(sprintf(
            'Switched to: <info>%s:%s</info>',
            $vault,
            $stage
        ));
    }
    
    protected function switchStage(string $stage): void
    {
        $this->context->setStage($stage);
        $this->output->writeln(sprintf(
            'Switched to stage: <info>%s</info>',
            $stage
        ));
    }
    
    protected function switchVault(string $vault): void
    {
        $this->context->setVault($vault);
        $this->output->writeln(sprintf(
            'Switched to vault: <info>%s</info>',
            $vault
        ));
    }
    
    protected function selectStage(): void
    {
        $stages = $this->context->getAvailableStages();
        $current = $this->context->getStage();
        
        $selected = select(
            label: 'Select a stage:',
            options: $stages,
            default: $current,
            hint: 'Use arrow keys to navigate, Enter to select'
        );
        
        if ($selected !== $current) {
            $this->switchStage($selected);
        } else {
            $this->output->writeln(sprintf(
                'Already on stage: <info>%s</info>',
                $current
            ));
        }
    }
    
    protected function selectVault(): void
    {
        $vaults = $this->context->getAvailableVaults();
        $current = $this->context->getVault();
        
        $selected = select(
            label: 'Select a vault:',
            options: $vaults,
            default: $current,
            hint: 'Use arrow keys to navigate, Enter to select'
        );
        
        if ($selected !== $current) {
            $this->switchVault($selected);
        } else {
            $this->output->writeln(sprintf(
                'Already on vault: <info>%s</info>',
                $current
            ));
        }
    }
    
    protected function getHelpSections(): array
    {
        return [
            '<comment>Secret Management</comment>' => [
                'get <key>' => 'Get a secret value (alias: g)',
                'set <key> <value>' => 'Set a secret (alias: s)',
                'delete <key>' => 'Delete a secret (alias: d)',
                'show' => 'Show all secrets (alias: ls)',
                'history <key>' => 'View secret history',
                'copy <key> [destination]' => 'Copy single secret (e.g., copy DB_PASS staging)',
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
                'info' => 'Show Keep information',
            ],
            '<comment>Other</comment>' => [
                'exit' => 'Exit the shell (or Ctrl+D)',
                'help' => 'Show this help message (alias: ?)',
                'clear' => 'Clear the screen (alias: cls)',
            ],
        ];
    }
}
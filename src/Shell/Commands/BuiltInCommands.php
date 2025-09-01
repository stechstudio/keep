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
        $this->register(['help', '?'], fn($args) => $this->help($args));
        $this->register(['context', 'ctx'], fn() => $this->context());
        $this->register('stage', fn($args) => $this->stage($args));
        $this->register('vault', fn($args) => $this->vault($args));
        $this->register(['use', 'u'], fn($args) => $this->use($args));
        $this->register('colors', fn() => $this->showColors());
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
    
    protected function help(array $args = []): void
    {
        // Check if specific command help is requested
        if (isset($args[0])) {
            $this->showCommandHelp($args[0]);
            return;
        }
        
        // Show general help
        $sections = $this->getHelpSections();
        
        $this->output->writeln('');
        $this->output->writeln('<info>Keep Shell Commands</info>');
        $this->output->writeln('');
        
        foreach ($sections as $title => $commands) {
            $this->output->writeln($title);
            
            $maxLength = max(array_map('strlen', array_keys($commands)));
            
            foreach ($commands as $command => $description) {
                $this->output->writeln(sprintf(
                    '  <command-name>%-' . ($maxLength + 2) . 's</command-name> <neutral>%s</neutral>',
                    $command,
                    $description
                ));
            }
            
            $this->output->writeln('');
        }
        
        $this->output->writeln('Type <command-name>help <command></command-name> for detailed information about a specific command.');
    }
    
    protected function context(): void
    {
        $this->output->writeln('');
        $this->output->writeln(sprintf(
            "Vault: <alert>%s</alert>\nStage: <alert>%s</alert>",
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
            'Switched to: <alert>%s:%s</alert>',
            $vault,
            $stage
        ));
    }
    
    protected function switchStage(string $stage): void
    {
        $this->context->setStage($stage);
        $this->output->writeln(sprintf(
            'Switched to stage: <alert>%s</alert>',
            $stage
        ));
    }
    
    protected function switchVault(string $vault): void
    {
        $this->context->setVault($vault);
        $this->output->writeln(sprintf(
            'Switched to vault: <alert>%s</alert>',
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
                'Already on stage: <context>%s</context>',
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
                'Already on vault: <alert>%s</alert>',
                $current
            ));
        }
    }
    
    protected function showCommandHelp(string $command): void
    {
        $help = $this->getCommandHelp();
        
        // Handle aliases
        $aliases = [
            'g' => 'get',
            's' => 'set',
            'd' => 'delete',
            'ls' => 'show',
            'u' => 'use',
            'ctx' => 'context',
            '?' => 'help',
            'cls' => 'clear',
            'q' => 'exit',
            'quit' => 'exit',
        ];
        
        $command = $aliases[$command] ?? $command;
        
        if (!isset($help[$command])) {
            $this->output->writeln("<error>No help available for command: $command</error>");
            $this->output->writeln("Type <command-name>help</command-name> to see all available commands.");
            return;
        }
        
        $this->output->writeln('');
        $this->output->writeln('<command-name>' . $help[$command]['usage'] . '</command-name>');
        $this->output->writeln('');
        $this->output->writeln('<neutral>' . $help[$command]['description'] . '</neutral>');
        
        if (isset($help[$command]['examples'])) {
            $this->output->writeln('');
            $this->output->writeln('<comment>Examples:</comment>');
            foreach ($help[$command]['examples'] as $example) {
                $this->output->writeln('  ' . $example);
            }
        }
        
        if (isset($help[$command]['options'])) {
            $this->output->writeln('');
            $this->output->writeln('<comment>Options:</comment>');
            foreach ($help[$command]['options'] as $option => $desc) {
                $this->output->writeln(sprintf('  <command-name>%-20s</command-name> <neutral>%s</neutral>', $option, $desc));
            }
        }
        
        if (isset($help[$command]['aliases'])) {
            $this->output->writeln('');
            $this->output->writeln('<comment>Aliases:</comment> ' . implode(', ', $help[$command]['aliases']));
        }
        
        if (isset($help[$command]['notes'])) {
            $this->output->writeln('');
            $this->output->writeln('<comment>Notes:</comment>');
            foreach ($help[$command]['notes'] as $note) {
                $this->output->writeln($note ? '  ' . $note : '');
            }
        }
    }
    
    protected function getCommandHelp(): array
    {
        return [
            'get' => [
                'usage' => 'get <key>',
                'description' => 'Retrieve a secret value from the current vault and stage.',
                'examples' => [
                    'get DB_PASSWORD',
                    'get API_KEY',
                    'g DB_HOST      # Using alias',
                ],
                'aliases' => ['g'],
            ],
            'set' => [
                'usage' => 'set <key> <value>',
                'description' => 'Create or update a secret in the current vault and stage.',
                'examples' => [
                    'set DB_PASSWORD "my-secure-pass"',
                    'set API_KEY sk-1234567890',
                    's DEBUG_MODE true      # Using alias',
                ],
                'aliases' => ['s'],
            ],
            'delete' => [
                'usage' => 'delete <key> [force]',
                'description' => 'Delete a secret from the current vault and stage.',
                'examples' => [
                    'delete OLD_KEY',
                    'delete API_KEY force      # Skip confirmation',
                    'd TEMP_SECRET      # Using alias',
                ],
                'aliases' => ['d'],
            ],
            'show' => [
                'usage' => 'show [unmask]',
                'description' => 'Display all secrets in the current vault and stage.',
                'examples' => [
                    'show',
                    'show unmask      # Show unmasked values',
                    'ls      # Using alias',
                ],
                'aliases' => ['ls'],
            ],
            'copy' => [
                'usage' => 'copy <key> [destination]',
                'description' => 'Copy a secret from the current context to another stage or vault:stage.',
                'examples' => [
                    'copy DB_PASSWORD staging',
                    'copy API_KEY aws:production',
                    'copy SECRET_KEY      # Prompts for destination',
                    'copy only DB_*       # Copy multiple secrets by pattern',
                ],
            ],
            'diff' => [
                'usage' => 'diff <stage1> <stage2>',
                'description' => 'Compare secrets between two stages in the current vault.',
                'examples' => [
                    'diff development staging',
                    'diff staging production',
                ],
            ],
            'history' => [
                'usage' => 'history <key>',
                'description' => 'View the version history of a secret.',
                'examples' => [
                    'history DB_PASSWORD',
                    'history API_KEY',
                ],
            ],
            'rename' => [
                'usage' => 'rename <old> <new> [force]',
                'description' => 'Rename a secret while preserving its value and metadata.',
                'examples' => [
                    'rename OLD_KEY NEW_KEY',
                    'rename API_KEY_V1 API_KEY force      # Skip confirmation',
                ],
            ],
            'search' => [
                'usage' => 'search <query> [unmask] [case-sensitive]',
                'description' => 'Search for secrets containing specific text in their values.',
                'examples' => [
                    'search "password"',
                    'search "api-key" unmask      # Show actual values',
                    'search "Token" case-sensitive      # Case-sensitive search',
                ],
            ],
            'stage' => [
                'usage' => 'stage [name]',
                'description' => 'Switch to a different stage or interactively select one.',
                'examples' => [
                    'stage production',
                    'stage      # Interactive selection',
                ],
            ],
            'vault' => [
                'usage' => 'vault [name]',
                'description' => 'Switch to a different vault or interactively select one.',
                'examples' => [
                    'vault aws-secrets',
                    'vault      # Interactive selection',
                ],
            ],
            'use' => [
                'usage' => 'use <vault:stage>',
                'description' => 'Switch both vault and stage at once.',
                'examples' => [
                    'use aws:production',
                    'use test:development',
                    'u ssm:staging      # Using alias',
                ],
                'aliases' => ['u'],
            ],
            'context' => [
                'usage' => 'context',
                'description' => 'Display the current vault and stage context.',
                'examples' => [
                    'context',
                    'ctx      # Using alias',
                ],
                'aliases' => ['ctx'],
            ],
            'export' => [
                'usage' => 'export [format]',
                'description' => 'Interactively export secrets with guided prompts for all options.',
                'examples' => [
                    'export           # Interactive mode - prompts for all options',
                    'export json      # Quick JSON format (still prompts for destination)',
                    'export env       # Quick env format (still prompts for destination)',
                ],
                'notes' => [
                    'The shell provides an interactive experience with prompts for:',
                    '  • Export mode (all secrets, template, or filtered)',
                    '  • Output format (env or JSON)',
                    '  • Destination (screen or file)',
                    '  • Template options and missing secret handling',
                    '',
                    'For non-interactive exports with all options, use the CLI:',
                    '  keep export --format=json --file=secrets.json --template=.env.template',
                ],
            ],
            'verify' => [
                'usage' => 'verify',
                'description' => 'Verify vault configuration, authentication, and permissions.',
                'examples' => [
                    'verify',
                ],
            ],
            'info' => [
                'usage' => 'info',
                'description' => 'Display information about the Keep configuration.',
                'examples' => [
                    'info',
                ],
            ],
            'help' => [
                'usage' => 'help [command]',
                'description' => 'Show help information for all commands or a specific command.',
                'examples' => [
                    'help',
                    'help get',
                    '? set      # Using alias',
                ],
                'aliases' => ['?'],
            ],
            'clear' => [
                'usage' => 'clear',
                'description' => 'Clear the terminal screen.',
                'examples' => [
                    'clear',
                    'cls      # Using alias',
                ],
                'aliases' => ['cls'],
            ],
            'exit' => [
                'usage' => 'exit',
                'description' => 'Exit the Keep shell.',
                'examples' => [
                    'exit',
                    'quit      # Using alias',
                    'q         # Using short alias',
                ],
                'aliases' => ['quit', 'q'],
            ],
        ];
    }
    
    protected function showColors(): void
    {
        $this->output->writeln('');
        $this->output->writeln('=== Shell Color Scheme ===');
        $this->output->writeln('');
        $this->output->writeln('<success>✓ Success message - operations completed successfully</success>');
        $this->output->writeln('<info>→ Info message - general information</info>');
        $this->output->writeln('<warning>⚠ Warning message - attention needed</warning>');
        $this->output->writeln('<error>✗ Error message - something went wrong</error>');
        $this->output->writeln('');
        $this->output->writeln('<context>ssm:production</context> - Vault and stage context');
        $this->output->writeln('<secret-name>DB_PASSWORD</secret-name> - Secret names');
        $this->output->writeln('<command-name>get</command-name> <neutral>- Command names</neutral>');
        $this->output->writeln('<suggestion>set</suggestion> - Command suggestions');
        $this->output->writeln('<neutral>This is neutral descriptive text</neutral>');
        $this->output->writeln('');
    }
    
    protected function getHelpSections(): array
    {
        return [
            '<comment>Secret Management</comment>' => [
                'get <key>' => 'Get a secret value (alias: g)',
                'set <key> <value>' => 'Set a secret (alias: s)',
                'delete <key> [force]' => 'Delete a secret (alias: d)',
                'show [unmask]' => 'Show all secrets (alias: ls)',
                'history <key>' => 'View secret history',
                'rename <old> <new>' => 'Rename a secret',
                'search <query>' => 'Search for secrets containing text',
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
                'export' => 'Export secrets interactively',
                'verify' => 'Verify vault setup and permissions',
                'info' => 'Show Keep information',
            ],
            '<comment>Other</comment>' => [
                'exit' => 'Exit the shell (or Ctrl+D)',
                'help' => 'Show this help message (alias: ?)',
                'clear' => 'Clear the screen (alias: cls)',
                'colors' => 'Show color scheme',
            ],
        ];
    }
}
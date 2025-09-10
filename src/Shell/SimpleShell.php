<?php

namespace STS\Keep\Shell;

use Illuminate\Console\Application;
use STS\Keep\Shell\Commands\BuiltInCommands;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class SimpleShell
{
    private ConsoleOutput $output;
    private CommandExecutor $executor;
    private BuiltInCommands $builtIn;
    private TabCompletion $completion;
    private CommandSuggestion $suggestion;
    private string $historyFile;
    
    
    public function __construct(
        private ShellContext $context,
        Application $application
    ) {
        $this->output = new ConsoleOutput();
        $this->executor = new CommandExecutor($context, $application);
        $this->builtIn = new BuiltInCommands($context, $this->output);
        $this->completion = new TabCompletion($context);
        $this->suggestion = new CommandSuggestion();
        $this->historyFile = $_SERVER['HOME'] . '/.keep_history';
        
        $this->initialize();
    }
    
    protected function initialize(): void
    {
        $this->configureOutputStyles();
        $this->setupReadline();
        $this->loadHistory();
    }
    
    protected function configureOutputStyles(): void
    {
        $formatter = $this->output->getFormatter();
        
        // Existing styles
        $formatter->setStyle('info', new OutputFormatterStyle('green'));
        $formatter->setStyle('comment', new OutputFormatterStyle('yellow'));
        $formatter->setStyle('error', new OutputFormatterStyle('red'));
        $formatter->setStyle('warning', new OutputFormatterStyle('yellow'));
        $formatter->setStyle('prompt', new OutputFormatterStyle('bright-blue', null, ['bold']));
        
        // New semantic styles
        $formatter->setStyle('success', new OutputFormatterStyle('green'));
        $formatter->setStyle('secret-name', new OutputFormatterStyle('bright-magenta'));
        $formatter->setStyle('command-name', new OutputFormatterStyle('bright-white'));
        $formatter->setStyle('context', new OutputFormatterStyle('bright-blue'));
        $formatter->setStyle('suggestion', new OutputFormatterStyle('bright-yellow'));
        $formatter->setStyle('neutral', new OutputFormatterStyle('gray'));
        $formatter->setStyle('alert', new OutputFormatterStyle('bright-red', null, ['bold']));
    }
    
    protected function setupReadline(): void
    {
        readline_completion_function([$this->completion, 'complete']);
    }
    
    protected function loadHistory(): void
    {
        if (file_exists($this->historyFile)) {
            readline_read_history($this->historyFile);
        }
    }
    
    public function run(): void
    {
        $this->showWelcome();
        $this->loop();
        $this->showGoodbye();
    }
    
    protected function showWelcome(): void
    {
        $this->output->writeln('');
        $this->output->writeln('<info>Welcome to Keep Shell v1.0.0</info>');
        $this->output->writeln("<neutral>Type 'help' for available commands or 'exit' to quit.</neutral>");
        $this->output->writeln('');
        $this->output->writeln(sprintf(
            "Current vault: <alert>%s</alert>\nCurrent environment: <alert>%s</alert>",
            $this->context->getVault(),
            $this->context->getEnv()
        ));
        //$this->output->writeln('<comment>Tab completion is available for commands and secret names!</comment>');
        $this->output->writeln('');
    }
    
    protected function showGoodbye(): void
    {
        $this->output->writeln('Goodbye!');
    }
    
    protected function loop(): void
    {
        while (true) {
            $input = readline($this->getPrompt());
            
            if ($input === false) {
                $this->output->writeln('');
                break;
            }
            
            $input = trim($input);
            
            if (empty($input)) {
                continue;
            }
            
            $this->recordHistory($input);
            $this->processInput($input);
            $this->output->writeln('');
        }
    }
    
    protected function getPrompt(): string
    {
        // Use bright blue for distinct visibility
        return sprintf(
            "\033[94m%s:%s\033[0m> ",
            $this->context->getVault(),
            $this->context->getEnv()
        );
    }
    
    protected function processInput(string $input): void
    {
        $parts = explode(' ', $input);
        $command = array_shift($parts);
        
        if ($this->builtIn->handle($command, $parts)) {
            return;
        }
        
        $this->executeKeepCommand($command, $parts, $input);
    }
    
    protected function executeKeepCommand(string $command, array $args, string $fullInput): void
    {
        if (!CommandRegistry::isKeepCommand($command)) {
            $this->handleUnknownCommand($command);
            return;
        }

        try {
            $exitCode = $this->executor->execute($fullInput);
            
            if ($exitCode !== 0) {
                $this->output->writeln('<error>Command failed.</error>');
            }
        } catch (\Exception $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }
    
    protected function handleUnknownCommand(string $command): void
    {
        $this->output->writeln(sprintf('<error>Command "%s" not found.</error>', $command));
        
        $suggestions = $this->suggestion->suggest($command);
        if (!empty($suggestions)) {
            $suggestedCommands = array_map(
                fn($cmd) => "<suggestion>$cmd</suggestion>",
                $suggestions
            );
            $this->output->writeln('Did you mean: ' . implode(', ', $suggestedCommands) . '?');
        } else {
            $this->output->writeln('Type <command-name>help</command-name> to see available commands.');
        }
    }
    
    protected function recordHistory(string $input): void
    {
        $sanitized = $this->sanitizeForHistory($input);
        
        if (!empty($sanitized)) {
            readline_add_history($sanitized);
            readline_write_history($this->historyFile);
        }
    }
    
    protected function sanitizeForHistory(string $input): string
    {
        $parts = explode(' ', $input);
        $command = $parts[0];
        
        // Resolve aliases
        $command = CommandRegistry::resolveAlias($command);
        
        // Redact secret values from set commands
        if ($command === 'set' && count($parts) >= 3) {
            return $parts[0] . ' ' . $parts[1] . ' [REDACTED]';
        }
        
        return $input;
    }
}
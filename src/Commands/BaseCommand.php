<?php

namespace STS\Keep\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use STS\Keep\Commands\Concerns\GathersInput;
use STS\Keep\Commands\Concerns\InteractsWithFilesystem;
use STS\Keep\Exceptions\KeepException;
use STS\Keep\Facades\Keep;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

use function Laravel\Prompts\error;
use function Laravel\Prompts\note;

abstract class BaseCommand extends Command
{
    use GathersInput, InteractsWithFilesystem;

    public function __construct(protected Filesystem $filesystem)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Configure semantic output styles
        $this->configureOutputStyles();
        
        // Check if Keep is initialized (unless this command doesn't require it)
        if ($this->requiresInitialization() && ! Keep::isInitialized()) {
            error('Keep is not initialized in this directory.');
            note('Run: keep configure');

            return self::FAILURE;
        }
        try {
            $result = $this->process();

            return match (true) {
                is_int($result) => $result,
                is_bool($result) => $result ? self::SUCCESS : self::FAILURE,
                default => self::SUCCESS,
            };
        } catch (KeepException $e) {
            $this->enhanceExceptionWithCommandContext($e);
            $e->renderConsole($this->line(...));

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error('An error occurred');
            $this->line($e->getMessage());

            return self::FAILURE;
        }
    }

    abstract protected function process();

    protected function requiresInitialization(): bool
    {
        return true;
    }

    /**
     * Enhance KeepException with any available command context that hasn't been set.
     */
    protected function enhanceExceptionWithCommandContext(KeepException $exception): void
    {
        $existing = $exception->getContext();

        // Build new context from command state, only if not already set
        $newContext = [];

        if (! isset($existing['vault']) && method_exists($this, 'vaultName')) {
            $vault = $this->vaultName();
            if ($vault !== null) {
                $newContext['vault'] = $vault;
            }
        }

        if (! isset($existing['env']) && method_exists($this, 'env')) {
            $env = $this->env();
            if ($env !== null) {
                $newContext['env'] = $env;
            }
        }

        if (! isset($existing['key']) && method_exists($this, 'key')) {
            $key = $this->key();
            if ($key !== null) {
                $newContext['key'] = $key;
            }
        }

        // Apply any found context
        if (! empty($newContext)) {
            $exception->withContext($newContext);
        }
    }
    
    /**
     * Configure semantic output styles for consistent coloring
     */
    protected function configureOutputStyles(): void
    {
        if (!$this->output) {
            return;
        }
        
        $formatter = $this->output->getFormatter();
        
        // Semantic styles matching the shell
        $formatter->setStyle('success', new OutputFormatterStyle('green'));
        $formatter->setStyle('secret-name', new OutputFormatterStyle('bright-magenta'));
        $formatter->setStyle('context', new OutputFormatterStyle('bright-blue'));
        $formatter->setStyle('neutral', new OutputFormatterStyle('gray'));
        $formatter->setStyle('command-name', new OutputFormatterStyle('bright-white'));
    }
    
    /**
     * Output a success message with semantic styling
     */
    public function success(string $message): void
    {
        $this->line("<success>✓ {$message}</success>");
    }
    
    /**
     * Output a message with secret name highlighting
     */
    public function secretInfo(string $key, string $message): void
    {
        $formattedKey = "<secret-name>{$key}</secret-name>";
        $formattedMessage = str_replace("[{$key}]", "[{$formattedKey}]", $message);
        $this->info($formattedMessage);
    }
    
    /**
     * Output a message with vault/env context highlighting
     */
    public function contextInfo(string $vault, string $env, string $message): void
    {
        $context = "<context>{$vault}:{$env}</context>";
        $this->info(str_replace("{$vault}:{$env}", $context, $message));
    }
    
    /**
     * Output vault context
     */
    public function showContext(string $vault, string $env): void
    {
        $this->line("Current context: <context>{$vault}:{$env}</context>");
    }
    
    /**
     * Output a neutral descriptive message
     */
    public function neutral(string $message): void
    {
        $this->line("<neutral>{$message}</neutral>");
    }
}

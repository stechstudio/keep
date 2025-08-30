<?php

namespace STS\Keep\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use STS\Keep\Commands\Concerns\GathersInput;
use STS\Keep\Commands\Concerns\InteractsWithFilesystem;
use STS\Keep\Exceptions\KeepException;
use STS\Keep\Facades\Keep;

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

        if (! isset($existing['stage']) && method_exists($this, 'stage')) {
            $stage = $this->stage();
            if ($stage !== null) {
                $newContext['stage'] = $stage;
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
}

<?php

namespace STS\Keep\Commands;

use Illuminate\Console\Command;
use STS\Keep\Commands\Concerns\GathersInput;
use STS\Keep\Commands\Concerns\InteractsWithFilesystem;
use STS\Keep\Exceptions\KeepException;
use STS\Keep\Facades\Keep;
use function Laravel\Prompts\error;
use function Laravel\Prompts\note;

abstract class BaseCommand extends Command
{
    use GathersInput, InteractsWithFilesystem;
    
    
    public function handle(): int
    {
        // Check if Keep is initialized (unless this command doesn't require it)
        if ($this->requiresInitialization() && !Keep::isInitialized()) {
            error('Keep is not initialized in this directory.');
            note('Run: keep configure');
            return self::FAILURE;
        }
        try {
            $result = $this->process();

            return match(true) {
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
        // Use reflection to check if context properties are already set
        $reflection = new \ReflectionClass($exception);

        // Helper to get property value safely
        $getProperty = function (string $name) use ($reflection, $exception) {
            try {
                $prop = $reflection->getProperty($name);
                $prop->setAccessible(true);
                return $prop->getValue($exception);
            } catch (\ReflectionException) {
                return null;
            }
        };

        // Helper to check if a context value is available in this command
        $getContextValue = function (string $property, callable $getter) {
            try {
                return property_exists($this, $property) && isset($this->{$property})
                    ? $this->{$property}
                    : $getter();
            } catch (\Exception) {
                return null;
            }
        };

        // Only set context if not already provided
        $vault = $getProperty('vault') ?: $getContextValue('vaultName', fn() => method_exists($this, 'vaultName') ? $this->vaultName() : null);
        $stage = $getProperty('stage') ?: $getContextValue('stage', fn() => method_exists($this, 'stage') ? $this->stage() : null);
        $key = $getProperty('key') ?: $getContextValue('key', fn() => method_exists($this, 'key') ? $this->key() : null);

        // Apply any found context
        if ($vault || $stage || $key) {
            $exception->withContext(
                vault: $vault,
                stage: $stage,
                key: $key
            );
        }
    }

    public function error($string, $verbosity = null)
    {
        parent::error($string, $verbosity);

        return false;
    }

    public function info($string, $verbosity = null)
    {
        parent::info($string, $verbosity);

        return true;
    }
}
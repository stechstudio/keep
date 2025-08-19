<?php

namespace STS\Keep\Commands;

use Illuminate\Console\Command;
use STS\Keep\Commands\Concerns\GathersInput;
use STS\Keep\Commands\Concerns\InteractsWithFilesystem;
use STS\Keep\Commands\Concerns\InteractsWithVaults;
use STS\Keep\Exceptions\KeepException;

abstract class AbstractCommand extends Command
{
    use GathersInput, InteractsWithFilesystem, InteractsWithVaults;

    public function handle(): int
    {
        if($this->input && $this->option('env')) {
            $this->error('The --env option is not to be used with Keep commands, as it changes the Laravel runtime environment. Use --stage to manage your environment-specific secrets.');
            return self::FAILURE;
        }

        try {
            $result = $this->process();

            return is_int($result) ? $result : self::SUCCESS;
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

    abstract public function process(): int;

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
}

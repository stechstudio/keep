<?php

namespace STS\Keep\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Concerns\ConfiguresPrompts;
use STS\Keep\KeepManager;
use function Laravel\Prompts\error;
use function Laravel\Prompts\note;

abstract class BaseCommand extends Command
{
    use ConfiguresPrompts;
    
    protected KeepManager $manager;
    
    public function setManager(KeepManager $manager): self
    {
        $this->manager = $manager;
        return $this;
    }
    
    public function handle(): int
    {
        // Check if Keep is initialized (unless this command doesn't require it)
        if ($this->requiresInitialization() && !$this->manager->isInitialized()) {
            error('Keep is not initialized in this directory.');
            note('Run: keep configure');
            return self::FAILURE;
        }
        
        $result = $this->process();

        return (is_int($result) || is_bool($result))
            ? (int) $result
            : self::SUCCESS;
    }
    
    abstract protected function process(): int;
    
    protected function requiresInitialization(): bool
    {
        return true;
    }
}
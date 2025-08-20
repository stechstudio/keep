<?php

namespace STS\Keep\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use STS\Keep\KeepManager;
use function Laravel\Prompts\error;
use function Laravel\Prompts\note;

abstract class BaseCommand extends Command
{
    protected KeepManager $manager;
    
    public function setManager(KeepManager $manager): self
    {
        $this->manager = $manager;
        return $this;
    }
    
    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Check if Keep is initialized (unless this command doesn't require it)
        if ($this->requiresInitialization() && !$this->manager->isInitialized()) {
            error('Keep is not initialized in this directory.');
            note('Run: keep configure');
            return Command::FAILURE;
        }
        
        $result = $this->handle($input, $output);

        return match (true) {
            is_bool($result) || is_int($result) => $result,
            default => 1
        };
    }
    
    abstract protected function handle(InputInterface $input, OutputInterface $output);
    
    protected function requiresInitialization(): bool
    {
        return true;
    }
}
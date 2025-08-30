<?php

namespace STS\Keep\Shell\Commands;

use Psy\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('list')
            ->setDescription('Show available Keep commands (same as help)');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Redirect to our help command
        $helpCommand = $this->getApplication()->find('help');
        $helpInput = new ArrayInput([]);
        
        return $helpCommand->run($helpInput, $output);
    }
}
<?php

namespace STS\Keep\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function Laravel\Prompts\info;

class TestCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->setName('test')
             ->setDescription('Test command to verify initialization check');
    }
    
    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        info('Keep is properly initialized! This command ran successfully.');
        return self::SUCCESS;
    }
}
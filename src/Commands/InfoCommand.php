<?php

namespace STS\Keep\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InfoCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('info')
             ->setDescription('Show Keep information and status');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Keep Secret Management Tool');
        
        $io->definitionList(
            ['Version' => $this->getApplication()->getVersion()],
            ['Working Directory' => getcwd()],
            ['PHP Version' => PHP_VERSION],
            ['Binary Path' => $_SERVER['argv'][0] ?? 'unknown']
        );
        
        // Check if we're in a Laravel project
        if (file_exists(getcwd() . '/artisan')) {
            $io->success('Laravel project detected');
        } else {
            $io->note('No Laravel project detected');
        }
        
        // Check for .keep directory
        if (is_dir(getcwd() . '/.keep')) {
            $io->success('.keep configuration directory found');
        } else {
            $io->warning('.keep configuration directory not found');
            $io->text('Run "keep configure" to set up vault configuration');
        }
        
        return Command::SUCCESS;
    }
}
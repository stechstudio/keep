<?php

namespace STS\Keep\Shell\Commands;

use Psy\Command\Command;
use STS\Keep\Shell\ShellContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StageCommand extends Command
{
    private ShellContext $context;
    
    public function __construct(ShellContext $context)
    {
        $this->context = $context;
        parent::__construct();
    }
    
    protected function configure()
    {
        $this
            ->setName('stage')
            ->setAliases(['s'])
            ->setDefinition([
                new InputArgument('name', InputArgument::OPTIONAL, 'Stage name to switch to'),
            ])
            ->setDescription('Switch to a different stage or show current stage');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        
        if (!$name) {
            $output->writeln(sprintf('<info>Current stage: %s</info>', $this->context->getStage()));
            $output->writeln('Available stages: <comment>' . implode(', ', $this->context->getAvailableStages()) . '</comment>');
            return 0;
        }
        
        $stages = $this->context->getAvailableStages();
        
        // Allow partial matching
        $matched = $this->findMatch($name, $stages);
        
        if ($matched) {
            $this->context->setStage($matched);
            $output->writeln(sprintf('<info>✓ Switched to stage: %s</info>', $matched));
        } else {
            $output->writeln(sprintf('<error>Unknown stage: %s</error>', $name));
            $output->writeln('Available stages: <comment>' . implode(', ', $stages) . '</comment>');
            return 1;
        }
        
        return 0;
    }
    
    private function findMatch(string $input, array $options): ?string
    {
        // Exact match first
        if (in_array($input, $options)) {
            return $input;
        }
        
        // Partial match
        foreach ($options as $option) {
            if (str_starts_with($option, $input)) {
                return $option;
            }
        }
        
        return null;
    }
}
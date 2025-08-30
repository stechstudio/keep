<?php

namespace STS\Keep\Shell\Commands;

use Psy\Command\Command;
use STS\Keep\Shell\ShellContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UseCommand extends Command
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
            ->setName('use')
            ->setAliases(['u'])
            ->setDefinition([
                new InputArgument('context', InputArgument::REQUIRED, 'Context in format vault:stage'),
            ])
            ->setDescription('Switch both vault and stage at once')
            ->setHelp(<<<'HELP'
Usage:
  use <vault:stage>

Arguments:
  context    Vault and stage to switch to (format: vault:stage)

Description:
  Switches both vault and stage in a single command.
  Supports partial matching for both vault and stage names.

Examples:
  use ssm:production     # Switch to SSM vault, production stage
  use test:dev           # Switch to test vault, development stage
  use sec:staging        # Switch to secretsmanager vault, staging
  use s:p                # Partial match (if unambiguous)
HELP
            );
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $contextStr = $input->getArgument('context');
        
        if (!str_contains($contextStr, ':')) {
            $output->writeln('<error>Invalid format. Use: vault:stage</error>');
            $output->writeln('Example: <comment>use ssm:production</comment>');
            return 1;
        }
        
        [$vault, $stage] = explode(':', $contextStr, 2);
        
        // Validate and switch vault
        $vaults = $this->context->getAvailableVaults();
        $matchedVault = $this->findMatch($vault, $vaults);
        
        if (!$matchedVault) {
            $output->writeln(sprintf('<error>Unknown vault: %s</error>', $vault));
            $output->writeln('Available vaults: <comment>' . implode(', ', $vaults) . '</comment>');
            return 1;
        }
        
        // Validate and switch stage
        $stages = $this->context->getAvailableStages();
        $matchedStage = $this->findMatch($stage, $stages);
        
        if (!$matchedStage) {
            $output->writeln(sprintf('<error>Unknown stage: %s</error>', $stage));
            $output->writeln('Available stages: <comment>' . implode(', ', $stages) . '</comment>');
            return 1;
        }
        
        // Switch both
        $this->context->setVault($matchedVault);
        $this->context->setStage($matchedStage);
        
        $output->writeln(sprintf(
            '<info>✓ Switched to %s:%s</info>',
            $matchedVault,
            $matchedStage
        ));
        
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
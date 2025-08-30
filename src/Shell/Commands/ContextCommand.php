<?php

namespace STS\Keep\Shell\Commands;

use Psy\Command\Command;
use STS\Keep\Data\Settings;
use STS\Keep\Shell\ShellContext;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ContextCommand extends Command
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
            ->setName('context')
            ->setAliases(['ctx'])
            ->setDescription('Show current shell context')
            ->setHelp(<<<'HELP'
Usage:
  context

Description:
  Displays the current shell context including:
  - Current vault and stage
  - Available vaults and stages
  - Default settings
  - Number of cached secrets for tab completion

Examples:
  context     # Show full context information
  ctx         # Same as above (alias)

Related Commands:
  stage       # Switch to a different stage
  vault       # Switch to a different vault  
  use         # Switch both vault and stage
HELP
            );
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $settings = Settings::load();
        
        $output->writeln('');
        $output->writeln('<info>Current Context:</info>');
        $output->writeln(sprintf('  Vault: <comment>%s</comment>', $this->context->getVault()));
        $output->writeln(sprintf('  Stage: <comment>%s</comment>', $this->context->getStage()));
        $output->writeln('');
        
        $output->writeln('<info>Available Options:</info>');
        $output->writeln('  Vaults: <comment>' . implode(', ', $this->context->getAvailableVaults()) . '</comment>');
        $output->writeln('  Stages: <comment>' . implode(', ', $this->context->getAvailableStages()) . '</comment>');
        $output->writeln('');
        
        $output->writeln('<info>Settings:</info>');
        $output->writeln(sprintf('  Default vault: <comment>%s</comment>', $settings->defaultVault()));
        $output->writeln(sprintf('  App namespace: <comment>%s</comment>', $settings->namespace()));
        $output->writeln('');
        
        // Show cached secret count if available
        $secretCount = count($this->context->getCachedSecretNames());
        if ($secretCount > 0) {
            $output->writeln(sprintf('<info>Cached secrets:</info> <comment>%d keys available for tab completion</comment>', $secretCount));
            $output->writeln('');
        }
        
        return 0;
    }
}
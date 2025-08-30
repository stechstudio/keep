<?php

namespace STS\Keep\Shell\Commands;

use Psy\Command\Command;
use STS\Keep\Shell\ShellContext;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VaultCommand extends Command
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
            ->setName('vault')
            ->setAliases(['v'])
            ->setDefinition([
                new InputArgument('name', InputArgument::OPTIONAL, 'Vault name to switch to'),
            ])
            ->setDescription('Switch to a different vault or show current vault');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        
        if (!$name) {
            $output->writeln(sprintf('<info>Current vault: %s</info>', $this->context->getVault()));
            $output->writeln('Available vaults: <comment>' . implode(', ', $this->context->getAvailableVaults()) . '</comment>');
            return 0;
        }
        
        $vaults = $this->context->getAvailableVaults();
        
        // Allow partial matching
        $matched = $this->findMatch($name, $vaults);
        
        if ($matched) {
            $this->context->setVault($matched);
            $output->writeln(sprintf('<info>✓ Switched to vault: %s</info>', $matched));
        } else {
            $output->writeln(sprintf('<error>Unknown vault: %s</error>', $name));
            $output->writeln('Available vaults: <comment>' . implode(', ', $vaults) . '</comment>');
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
<?php

namespace STS\Keep\Shell\Commands;

use Psy\Command\HelpCommand as PsyHelpCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HelpCommand extends PsyHelpCommand
{
    protected function configure()
    {
        parent::configure();
        $this->setDescription('Show available Keep commands')
            ->setHelp(<<<'HELP'
Usage:
  help [<command>]

Arguments:
  command    Command name to get help for (optional)

Description:
  Without arguments, shows all available Keep shell commands.
  With a command name, shows detailed help for that command.

Examples:
  help           # Show all available commands
  help copy      # Show detailed help for copy command
  help set       # Show detailed help for set command
  help diff      # Show detailed help for diff command
HELP
            );
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // If asking for help on a specific command, use parent implementation
        if ($input->getArgument('command_name')) {
            return parent::execute($input, $output);
        }
        
        // Otherwise show our custom help
        $output->writeln('');
        $output->writeln('<info>Keep Shell Commands</info>');
        $output->writeln('');
        
        $commands = [
            '<comment>Secret Management</comment>' => [
                'get <key>' => 'Get a secret value (alias: g)',
                'set <key> <value>' => 'Set a secret (alias: s)',  
                'delete <key>' => 'Delete a secret (alias: d)',
                'show' => 'Show all secrets (aliases: l, ls)',
                'history <key>' => 'View secret history',
                'copy <key>' => 'Copy single secret',
                'copy only <pattern>' => 'Copy secrets matching pattern',
                'diff <stage1> <stage2>' => 'Compare secrets between stages',
            ],
            '<comment>Context Management</comment>' => [
                'stage <name>' => 'Switch to a different stage',
                'vault <name>' => 'Switch to a different vault',
                'use <vault:stage>' => 'Switch both vault and stage (alias: u)',
                'context' => 'Show current context (alias: ctx)',
            ],
            '<comment>Analysis & Export</comment>' => [
                'export' => 'Export secrets to .env format',
                'verify' => 'Verify template placeholders',
                'info' => 'Show vault information',
            ],
            '<comment>Other</comment>' => [
                'exit' => 'Exit the shell (or Ctrl+D)',
                'help' => 'Show this help message (alias: ?)',
            ],
        ];
        
        foreach ($commands as $section => $sectionCommands) {
            $output->writeln($section);
            
            $maxLen = max(array_map('strlen', array_keys($sectionCommands)));
            
            foreach ($sectionCommands as $command => $description) {
                $output->writeln(sprintf(
                    '  <info>%-' . ($maxLen + 2) . 's</info> %s',
                    $command,
                    $description
                ));
            }
            
            $output->writeln('');
        }
        
        $output->writeln('<comment>Options & Examples:</comment>');
        $output->writeln('  <info>show unmask</info>              - Show secrets with unmasked values');
        $output->writeln('  <info>show json</info>                - Output in JSON format');
        $output->writeln('  <info>show env unmask</info>          - Export format with real values');
        $output->writeln('  <info>show only DB_*</info>           - Filter by pattern');
        $output->writeln('  <info>get NAME raw</info>             - Get raw value without table');
        $output->writeln('  <info>copy only DB_*</info>           - Copy matching secrets');
        $output->writeln('  <info>diff staging prod unmask</info> - Compare with real values');
        $output->writeln('');
        $output->writeln('<comment>Tips:</comment>');
        $output->writeln('  • Use TAB for command and secret name completion');
        $output->writeln('  • Commands remember your current vault and stage context');
        $output->writeln('  • Options are just words: <info>show unmask json</info>');
        $output->writeln('  • Use PHP code directly for advanced operations');
        $output->writeln('');
        
        return 0;
    }
}
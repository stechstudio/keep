<?php

namespace STS\Keep\Shell\Commands;

use Psy\Command\Command;
use STS\Keep\Shell\CommandExecutor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class KeepProxyCommand extends Command
{
    private CommandExecutor $executor;
    private string $keepCommand;
    private array $aliases;
    
    public function __construct(CommandExecutor $executor, string $keepCommand, array $aliases = [])
    {
        $this->executor = $executor;
        $this->keepCommand = $keepCommand;
        $this->aliases = $aliases;
        parent::__construct();
    }
    
    protected function configure()
    {
        $this
            ->setName($this->keepCommand)
            ->setAliases($this->aliases)
            ->setDefinition([
                new InputArgument('args', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Arguments for the command'),
            ])
            ->setDescription("Execute Keep {$this->keepCommand} command");
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Build the command string
        $args = $input->getArgument('args');
        $commandString = $this->keepCommand;
        
        // Parse arguments to extract options in natural syntax
        $positionalArgs = [];
        $options = [];
        
        if (!empty($args)) {
            $this->parseNaturalSyntax($args, $positionalArgs, $options);
        }
        
        // Add positional arguments
        if (!empty($positionalArgs)) {
            $escapedArgs = array_map(function($arg) {
                // If it contains spaces or special characters, quote it
                if (preg_match('/[\s=]/', $arg)) {
                    return '"' . str_replace('"', '\"', $arg) . '"';
                }
                return $arg;
            }, $positionalArgs);
            
            $commandString .= ' ' . implode(' ', $escapedArgs);
        }
        
        // Add options from natural syntax
        foreach ($options as $name => $value) {
            if ($value === true) {
                $commandString .= " --{$name}";
            } else {
                $commandString .= " --{$name}={$value}";
            }
        }
        
        return $this->executor->execute($commandString);
    }
    
    /**
     * Parse natural syntax like "show unmask json" into positional args and options
     */
    private function parseNaturalSyntax(array $args, array &$positionalArgs, array &$options): void
    {
        // Define known option keywords for each command
        $knownOptions = [
            'show' => [
                'unmask' => ['unmask', true],
                'json' => ['format', 'json'],
                'env' => ['format', 'env'],
                'table' => ['format', 'table'],
                'only' => ['only', 'next'],  // 'next' means take next arg as value
                'except' => ['except', 'next'],
            ],
            'get' => [
                'raw' => ['format', 'raw'],
                'json' => ['format', 'json'],
                'table' => ['format', 'table'],
            ],
            'diff' => [
                'unmask' => ['unmask', true],
            ],
            'export' => [
                'json' => ['format', 'json'],
                'env' => ['format', 'env'],
                'output' => ['output', 'next'],
                'template' => ['template', 'next'],
            ],
            'copy' => [
                'only' => ['only', 'next'],
                'except' => ['except', 'next'],
                'key' => ['key', 'next'],
            ],
            'stage:add' => [
                'copy-from' => ['copy-from', 'next'],
            ],
        ];
        
        $commandOptions = $knownOptions[$this->keepCommand] ?? [];
        
        $i = 0;
        while ($i < count($args)) {
            $arg = $args[$i];
            
            // Check if this argument is a known option keyword
            if (isset($commandOptions[$arg])) {
                [$optionName, $optionValue] = $commandOptions[$arg];
                
                if ($optionValue === 'next') {
                    // This option expects the next argument as its value
                    if (isset($args[$i + 1]) && !isset($commandOptions[$args[$i + 1]])) {
                        $options[$optionName] = $args[$i + 1];
                        $i += 2;
                    } else {
                        // No value provided, treat as positional
                        $positionalArgs[] = $arg;
                        $i++;
                    }
                } else {
                    // This is a boolean flag or has a fixed value
                    $options[$optionName] = $optionValue;
                    $i++;
                }
            } else {
                // Regular positional argument
                $positionalArgs[] = $arg;
                $i++;
            }
        }
    }
}
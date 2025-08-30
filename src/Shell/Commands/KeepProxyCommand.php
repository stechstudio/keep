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
            ->setDescription($this->getCommandDescription())
            ->setHelp($this->getCommandHelp());
    }
    
    private function getCommandDescription(): string
    {
        return match($this->keepCommand) {
            'get' => 'Retrieve a secret value',
            'set' => 'Set a secret value',
            'delete' => 'Delete a secret',
            'show' => 'Show all secrets in current context',
            'copy' => 'Copy secrets between stages or vaults',
            'diff' => 'Compare secrets across stages',
            'export' => 'Export secrets to a file',
            'import' => 'Import secrets from a file',
            'history' => 'View secret history',
            'verify' => 'Verify template placeholders',
            'info' => 'Show vault and stage information',
            'vault:list' => 'List all configured vaults',
            'vault:info' => 'Show current vault details',
            'vault:add' => 'Add a new vault configuration',
            'stage:add' => 'Create a new stage',
            'stage:list' => 'List all stages in current vault',
            'configure' => 'Configure Keep settings',
            default => "Execute Keep {$this->keepCommand} command"
        };
    }
    
    private function getCommandHelp(): string
    {
        return match($this->keepCommand) {
            'get' => <<<'HELP'
Usage:
  get <key> [raw|json|table]

Arguments:
  key     The secret key to retrieve

Options:
  raw     Output raw value without formatting
  json    Output in JSON format
  table   Output in table format (default)

Examples:
  get API_KEY
  get DATABASE_URL raw
  get AWS_SECRET json
HELP,
            
            'set' => <<<'HELP'
Usage:
  set <key> <value>

Arguments:
  key     The secret key to set
  value   The secret value

Examples:
  set API_KEY "sk-1234567890"
  set DATABASE_URL "postgres://user:pass@host/db"
  set DEBUG_MODE true
HELP,
            
            'delete' => <<<'HELP'
Usage:
  delete <key>

Arguments:
  key     The secret key to delete

Examples:
  delete OLD_API_KEY
  delete TEMP_PASSWORD
HELP,
            
            'show' => <<<'HELP'
Usage:
  show [unmask] [json|env|table] [only <pattern>] [except <pattern>]

Options:
  unmask           Show full secret values instead of masked
  json             Output in JSON format
  env              Output in .env format
  table            Output in table format (default)
  only <pattern>   Only show secrets matching pattern
  except <pattern> Exclude secrets matching pattern

Examples:
  show
  show unmask
  show json unmask
  show env
  show only DB_*
  show except *_SECRET unmask
HELP,
            
            'copy' => <<<'HELP'
Usage:
  copy <key>
  copy only <pattern> [except <pattern>]

Arguments:
  key              Single secret key to copy

Options:
  only <pattern>   Copy secrets matching pattern (bulk copy)
  except <pattern> Exclude secrets matching pattern (with only)

Notes:
  - You'll be prompted to select destination interactively
  - Use current context as source
  - For bulk operations, use "only" with patterns

Examples:
  copy API_KEY
  copy only DB_*
  copy only * except *_SECRET
  copy only AWS_* except AWS_REGION
HELP,
            
            'diff' => <<<'HELP'
Usage:
  diff [stage1] [stage2] [...] [unmask]

Arguments:
  stage1, stage2   Stages to compare (defaults to all stages)

Options:
  unmask           Show actual values instead of masked

Examples:
  diff                          # Compare all stages
  diff staging production       # Compare two stages
  diff dev staging prod unmask # Compare three stages, show values
HELP,
            
            'export' => <<<'HELP'
Usage:
  export [json|env] [output <file>] [template <file>]

Options:
  json             Export in JSON format
  env              Export in .env format (default)
  output <file>    Write to file instead of stdout
  template <file>  Use template file for export

Examples:
  export
  export json
  export output secrets.env
  export template .env.example output .env
HELP,
            
            'import' => <<<'HELP'
Usage:
  import <file>

Arguments:
  file    Path to file to import (.env or .json)

Examples:
  import .env
  import secrets.json
  import /path/to/config.env
HELP,
            
            'history' => <<<'HELP'
Usage:
  history <key>

Arguments:
  key     The secret key to view history for

Examples:
  history API_KEY
  history DATABASE_PASSWORD
HELP,
            
            'verify' => <<<'HELP'
Usage:
  verify [template <file>]

Options:
  template <file>  Template file to verify (defaults to .env.example)

Examples:
  verify
  verify template .env.production
  verify template config/app.env
HELP,
            
            'info' => <<<'HELP'
Usage:
  info

Shows current vault and stage information including:
  - Current vault name and type
  - Current stage
  - Number of secrets
  - Vault configuration details

Examples:
  info
HELP,
            
            'vault:list' => <<<'HELP'
Usage:
  vault:list

Lists all configured vaults with their types and stages.

Examples:
  vault:list
HELP,
            
            'vault:info' => <<<'HELP'
Usage:
  vault:info

Shows detailed information about the current vault.

Examples:
  vault:info
HELP,
            
            'vault:add' => <<<'HELP'
Usage:
  vault:add

Interactively add a new vault configuration.
You'll be prompted for vault type and configuration details.

Examples:
  vault:add
HELP,
            
            'stage:add' => <<<'HELP'
Usage:
  stage:add <name> [copy-from <stage>]

Arguments:
  name             Name of the new stage

Options:
  copy-from <stage>  Copy all secrets from existing stage

Examples:
  stage:add testing
  stage:add qa copy-from staging
HELP,
            
            'stage:list' => <<<'HELP'
Usage:
  stage:list

Lists all stages in the current vault.

Examples:
  stage:list
HELP,
            
            'configure' => <<<'HELP'
Usage:
  configure

Interactively configure Keep settings including:
  - Default vault
  - Default stage
  - Laravel integration mode
  - Other preferences

Examples:
  configure
HELP,
            
            default => ''
        };
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
        // Special handling for diff command - positional args are stages
        if ($this->keepCommand === 'diff') {
            $stages = [];
            foreach ($args as $arg) {
                if ($arg === 'unmask') {
                    $options['unmask'] = true;
                } else {
                    // Treat as a stage name
                    $stages[] = $arg;
                }
            }
            if (!empty($stages)) {
                $options['stage'] = implode(',', $stages);
            }
            return;
        }
        
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
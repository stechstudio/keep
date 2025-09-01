<?php

namespace STS\Keep\Shell;

class TabCompletion
{
    
    public function __construct(private ShellContext $context)
    {
    }
    
    public function complete(string $input, int $index): array
    {
        $info = readline_info();
        $line = substr($info['line_buffer'], 0, $info['end']);
        $parts = explode(' ', $line);
        
        if (count($parts) === 1) {
            return $this->filterByPrefix(CommandRegistry::getAllCommands(), $parts[0]);
        }
        
        $command = $this->resolveCommand($parts[0]);
        $currentArg = end($parts);
        $argPosition = count($parts) - 2; // 0-based position of current argument
        
        return $this->getCompletionsForCommand($command, $currentArg, $argPosition);
    }
    
    protected function resolveCommand(string $command): string
    {
        return CommandRegistry::resolveAlias($command);
    }
    
    protected function getCompletionsForCommand(string $command, string $prefix, int $argPosition): array
    {
        // Special handling for copy command
        if ($command === 'copy') {
            if ($argPosition === 0) {
                // First argument: secret names
                return $this->getSecretCompletions($prefix);
            } elseif ($argPosition === 1) {
                // Second argument: destination (stage or vault:stage)
                return array_merge(
                    $this->getStageCompletions($prefix),
                    $this->getContextCompletions($prefix)
                );
            }
            return [];
        }
        
        return match ($command) {
            'get', 'set', 'delete', 'history' => $this->getSecretCompletions($prefix),
            'stage', 'diff' => $this->getStageCompletions($prefix),
            'vault' => $this->getVaultCompletions($prefix),
            'use' => $this->getContextCompletions($prefix),
            'show' => $this->getShowCompletions($prefix),
            default => [],
        };
    }
    
    protected function getSecretCompletions(string $prefix): array
    {
        $secrets = $this->context->getCachedSecretNames();
        return $this->filterByPrefix($secrets, $prefix);
    }
    
    protected function getStageCompletions(string $prefix): array
    {
        $stages = $this->context->getAvailableStages();
        return $this->filterByPrefix($stages, $prefix);
    }
    
    protected function getVaultCompletions(string $prefix): array
    {
        $vaults = $this->context->getAvailableVaults();
        return $this->filterByPrefix($vaults, $prefix);
    }
    
    protected function getContextCompletions(string $prefix): array
    {
        $contexts = [];
        $vaults = $this->context->getAvailableVaults();
        $stages = $this->context->getAvailableStages();
        
        foreach ($vaults as $vault) {
            foreach ($stages as $stage) {
                $contexts[] = "$vault:$stage";
            }
        }
        
        return $this->filterByPrefix($contexts, $prefix);
    }
    
    protected function getShowCompletions(string $prefix): array
    {
        if (in_array($prefix, ['only', 'except'])) {
            return [];
        }
        
        $keywords = [];
        
        if (str_starts_with('only', $prefix)) {
            $keywords[] = 'only';
        }
        
        if (str_starts_with('except', $prefix)) {
            $keywords[] = 'except';
        }
        
        return $keywords;
    }
    
    protected function filterByPrefix(array $items, string $prefix): array
    {
        if (empty($prefix)) {
            return $items;
        }
        
        return array_values(array_filter(
            $items,
            fn($item) => str_starts_with($item, $prefix)
        ));
    }
}
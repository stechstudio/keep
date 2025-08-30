<?php

namespace STS\Keep\Shell\Completers;

use STS\Keep\Shell\ShellContext;

class StageCompleter
{
    private ShellContext $context;
    
    public function __construct(ShellContext $context)
    {
        $this->context = $context;
    }
    
    public function complete(string $input, string $command = ''): array
    {
        // Provide stage completion for relevant commands
        $stageCommands = ['stage', 's', '--stage'];
        
        // Also check if we're completing a --to or --from parameter
        $isStageContext = in_array($command, $stageCommands) 
            || str_contains($input, '--stage=')
            || str_contains($input, '--to=')
            || str_contains($input, '--from=');
            
        if (!$isStageContext && $command !== 'stage' && $command !== 's') {
            return [];
        }
        
        $stages = $this->context->getAvailableStages();
        
        if (empty($input)) {
            return $stages;
        }
        
        $matches = array_filter($stages, function($stage) use ($input) {
            return stripos($stage, $input) === 0;
        });
        
        return array_values($matches);
    }
}
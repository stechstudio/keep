<?php

namespace STS\Keep\Shell;

class CommandSuggestion
{
    public function suggest(string $input): array
    {
        $input = strtolower($input);
        $suggestions = [];
        $commands = CommandRegistry::getAllCommands();
        
        // First, add exact prefix matches
        foreach ($commands as $command) {
            if (str_starts_with($command, $input)) {
                $suggestions[] = $command;
            }
        }
        
        // Then add fuzzy matches using Levenshtein distance
        foreach ($commands as $command) {
            if (!in_array($command, $suggestions) && levenshtein($input, $command) <= 2) {
                $suggestions[] = $command;
            }
        }
        
        return array_unique($suggestions);
    }
    
    public function formatSuggestions(array $suggestions): string
    {
        if (empty($suggestions)) {
            return '';
        }
        
        return sprintf(' Did you mean: %s?', implode(', ', $suggestions));
    }
}
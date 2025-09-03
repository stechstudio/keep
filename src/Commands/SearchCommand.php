<?php

namespace STS\Keep\Commands;

use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class SearchCommand extends BaseCommand
{
    public $signature = 'search 
        {query? : Text to search for in secret values}
        {--unmask : Show actual secret values in results}
        {--case-sensitive : Make the search case-sensitive}
        {--format=table : Output format (table or json)} 
        {--only= : Only include keys matching this pattern (e.g. DB_*)} 
        {--except= : Exclude keys matching this pattern (e.g. MAIL_*)}
        {--vault= : The vault to use}
        {--stage= : The stage to use}';

    public $description = 'Search for secrets containing specific text in their values';

    public function process()
    {
        $query = $this->argument('query') ?? text('Search query', required: true);
        $unmask = $this->option('unmask');
        $caseSensitive = $this->option('case-sensitive');
        $format = $this->option('format');
        
        $context = $this->vaultContext();
        $vault = $context->createVault();
        
        // Get all secrets
        $secrets = $vault->list()->filterByPatterns(
            only: $this->option('only'),
            except: $this->option('except')
        );

        if ($secrets->isEmpty()) {
            $this->info('No secrets found to search.');
            return self::SUCCESS;
        }

        // Search through values
        $matches = $secrets->filter(function ($secret) use ($query, $caseSensitive) {
            $value = $secret->value();
            
            if (!$caseSensitive) {
                $value = strtolower($value);
                $query = strtolower($query);
            }
            
            return str_contains($value, $query);
        });

        if ($matches->isEmpty()) {
            $this->info(sprintf('No secrets found containing "%s" in <context>%s:%s</context>',
                $this->argument('query'),
                $context->vault,
                $context->stage
            ));
            return self::SUCCESS;
        }

        // Prepare results
        $results = $matches->map(function ($secret) use ($unmask, $query, $caseSensitive, $format) {
            // For table format, use formatted values with word wrapping
            if ($format === 'table') {
                if ($unmask) {
                    // Apply highlighting then wrap
                    $highlightedValue = $this->highlightMatch($secret->value(), $query, $caseSensitive);
                    // Create a temporary secret with highlighted value to use formatting
                    $tempSecret = new \STS\Keep\Data\Secret(
                        $secret->key(), 
                        $highlightedValue,
                        skipValidation: true
                    );
                    return $tempSecret->forTable();
                }
                
                // Return masked value with formatting
                $maskedSecret = $secret->withMaskedValue();
                return $maskedSecret->forTable();
            }
            
            // For JSON format, return raw values
            if ($unmask) {
                return [
                    'key' => $secret->key(),
                    'value' => $secret->value(),
                    'revision' => $secret->revision()
                ];
            }
            
            // Return masked value for JSON
            $maskedSecret = $secret->withMaskedValue();
            return [
                'key' => $maskedSecret->key(),
                'value' => $maskedSecret->value(),
                'revision' => $maskedSecret->revision()
            ];
        });

        // Output results
        $this->line(sprintf('Found %d secret(s) containing "%s" in <context>%s:%s</context>:',
            $matches->count(),
            $this->argument('query'),
            $context->vault,
            $context->stage
        ));
        $this->newLine();

        match ($format) {
            'json' => $this->line($results->toJson(JSON_PRETTY_PRINT)),
            default => table(['Key', 'Value', 'Revision'], $results->toArray())
        };

        return self::SUCCESS;
    }

    /**
     * Highlight matching text in the value
     */
    private function highlightMatch(string $value, string $query, bool $caseSensitive): string
    {
        if (!$caseSensitive) {
            // Case-insensitive replacement
            $pattern = '/(' . preg_quote($query, '/') . ')/i';
        } else {
            $pattern = '/(' . preg_quote($query, '/') . ')/';
        }
        
        // Use bright yellow background with black text for visibility
        // ANSI: \033[30;103m = black text on bright yellow background
        // Reset: \033[0m
        return preg_replace($pattern, "\033[30;103m$1\033[0m", $value);
    }
}
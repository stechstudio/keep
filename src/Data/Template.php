<?php

namespace STS\Keep\Data;

use STS\Keep\Data\Collections\SecretCollection;
use STS\Keep\Data\Collections\PlaceholderCollection;
use STS\Keep\Data\Concerns\FormatsEnvValues;
use STS\Keep\Data\Placeholder;
use STS\Keep\Enums\MissingSecretStrategy;
use STS\Keep\Exceptions\ExceptionFactory;

class Template
{
    use FormatsEnvValues;

    public function __construct(protected string $contents) {}

    public function isEmpty(): bool
    {
        return empty(trim($this->contents));
    }

    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    public function merge(SecretCollection $secrets, MissingSecretStrategy $strategy): string
    {
        $pattern = $this->pattern();

        return preg_replace_callback($pattern, function ($matches) use ($secrets, $strategy) {
            // Calculate line number by counting newlines before this match
            $beforeMatch = strstr($this->contents, $matches[0], true);
            $lineNumber = $beforeMatch !== false ? substr_count($beforeMatch, "\n") + 1 : 1;

            $vaultName = $matches['vault'];
            $path = $matches['path'] ?: $matches['key'];
            
            // Find secret that matches both key and vault name
            $secret = $secrets->firstWhere(function (Secret $secret) use ($path, $vaultName) {
                return $secret->key() === $path && $secret->vault()?->name() === $vaultName;
            });

            if (! $secret) {
                return match ($strategy) {
                    MissingSecretStrategy::FAIL => throw ExceptionFactory::secretNotFoundInTemplate(
                        $matches['key'], 
                        $vaultName, 
                        $path, 
                        $lineNumber
                    ),
                    MissingSecretStrategy::REMOVE => '# Removed missing secret: '.$matches['key'],
                    MissingSecretStrategy::BLANK => $matches['key'].'=',
                    MissingSecretStrategy::SKIP => $matches[0],
                };
            }

            $value = $secret->value();
            $formattedValue = $this->formatEnvValue($value);

            // Preserve original formatting from template
            return $matches['lead'].
                   $matches['key'].
                   $matches['mid'].
                   $formattedValue.
                   $matches['trail'].
                   ($matches['comment'] ?? '');
        }, $this->contents);
    }

    /**
     * Extract all vault names referenced in this template's placeholders.
     * 
     * @return array<string> Unique vault names found in placeholders
     */
    public function allReferencedVaults(): array
    {
        if($this->isEmpty()) {
            return [];
        }

        $pattern = '/\{([A-Za-z0-9_-]+)(?::[^}]*)?\}/';
        preg_match_all($pattern, $this->contents, $matches);
        
        return array_unique($matches[1] ?? []);
    }

    /**
     * Extract all placeholders from the template content.
     * 
     * @return PlaceholderCollection Collection of Placeholder objects
     */
    public function placeholders(): PlaceholderCollection
    {
        if ($this->isEmpty()) {
            return new PlaceholderCollection();
        }

        $placeholders = [];
        $lines = explode("\n", $this->contents);
        $pattern = $this->pattern();

        foreach ($lines as $lineNumber => $line) {
            if (preg_match_all($pattern, $line, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $placeholders[] = Placeholder::fromMatch($match, $lineNumber + 1, $line);
                }
            }
        }

        return new PlaceholderCollection($placeholders);
    }

    /**
     * Provides a regex pattern to match env keys with placeholders in the env template.
     * Placeholder syntax: {VAULT_SLUG[:PATH][|ATTR[|ATTR...]]}
     * Examples:
     * - DB_PASSWORD={ssm:DB_PASSWORD}
     * - API_KEY='{secretsmanager:API_KEY|label=primary}'
     * - MAIL_PASSWORD="{ssm-usw-2}"
     */
    protected function pattern()
    {
        return '~^
            (?P<lead>\s*)                                  # leading whitespace
            (?P<key>[A-Za-z_][A-Za-z0-9_]*)                # ENV KEY (cannot start with number)
            (?P<mid>\s*=\s*)                               # equals w/ optional spacing
            (?P<quote>["\'])?                              # optional opening quote
            \{(?P<vault>[A-Za-z0-9_-]+)                   # {vault_slug (capture any vault name)
                (?:                                        # OPTIONAL :PATH[|ATTR...]
                    :(?P<path>(?!\/)[A-Za-z0-9_.\-\/]+)    # relative PATH (no leading /)
                    (?P<attrblock>(?:\|[^}|]+)*)           # |ATTR or |k:v, zero or more
                )?                                         # path/attrs block optional (allows {vault})
            \}                                             # closing brace
            (?P=quote)?                                    # optional matching close-quote
            (?P<trail>[ \t]*)                              # trailing spaces
            (?P<comment>\#.*)?                             # optional inline comment
        $~mx';
    }
}

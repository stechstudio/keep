<?php

namespace STS\Keep\Data;

use Illuminate\Support\Collection;
use STS\Keep\Enums\MissingSecretStrategy;
use STS\Keep\Exceptions\SecretNotFoundException;

class Template
{
    public function __construct(protected string $contents)
    {
    }

    public function isEmpty(): bool
    {
        return empty(trim($this->contents));
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function merge(string $slug, SecretsCollection $secrets, MissingSecretStrategy $strategy): string
    {
        $pattern = $this->pattern($slug);

        return preg_replace_callback($pattern, function($matches) use ($secrets, $strategy) {
            $path = $matches['path'] ?: $matches['key'];
            $secret = $secrets->firstWhere(fn(Secret $secret) => $secret->key() === $path);

            if(!$secret) {
                return match($strategy) {
                    MissingSecretStrategy::FAIL => throw new SecretNotFoundException("Unable to find secret for key [{$path}]"),
                    MissingSecretStrategy::REMOVE => '# Removed missing secret: ' . $matches[0],
                    MissingSecretStrategy::BLANK => $matches['key'] . '=',
                    MissingSecretStrategy::SKIP => $matches[0],
                };
            }

            return trim($matches['key'] . '="' . $secret->value() . '" ' . ($matches['comment'] ?? ''));
        }, $this->contents);
    }

    /**
     * Provides a regex pattern to match env keys with placeholders in the env template.
     * Placeholder syntax: {SLUG[:PATH][|ATTR[|ATTR...]]}
     * Examples:
     * - DB_PASSWORD={aws-ssm:DB_PASSWORD}
     * - API_KEY='{aws-ssm:API_KEY|label=primary}'
     * - MAIL_PASSWORD="{aws-ssm}"
     */
    protected function pattern(string $slug)
    {
        return '~^
            (?P<lead>\s*)                                  # leading whitespace
            (?P<key>[A-Z0-9_]+)                            # ENV KEY
            (?P<mid>\s*=\s*)                               # equals w/ optional spacing
            (?P<quote>["\'])?                              # optional opening quote
            \{'.$slug.'                                    # {slug  (driver slug)
                (?:                                        # OPTIONAL :PATH[|ATTR...]
                    :(?P<path>(?!\/)[A-Za-z0-9_.\-\/]+)    # relative PATH (no leading /)
                    (?P<attrblock>(?:\|[^}|]+)*)           # |ATTR or |k:v, zero or more
                )?                                         # path/attrs block optional (allows {slug})
            \}                                             # closing brace
            (?P=quote)?                                    # optional matching close-quote
            (?P<trail>[ \t]*)                              # trailing spaces
            (?P<comment>\#.*)?                             # optional inline comment
        $~mx';
    }
}
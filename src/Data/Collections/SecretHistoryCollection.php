<?php

namespace STS\Keep\Data\Collections;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use STS\Keep\Data\Filters\DateFilter;
use STS\Keep\Data\Filters\StringFilter;
use STS\Keep\Data\SecretHistory;

class SecretHistoryCollection extends Collection
{
    /**
     * Filter entries by user (partial match - case insensitive)
     */
    public function filterByUser(?StringFilter $filter): static
    {
        if (! $filter) {
            return $this;
        }

        return $this->filter(
            fn (SecretHistory $history) => $history->lastModifiedUser() && Str::contains(strtolower($history->lastModifiedUser()), strtolower($filter->value()))
        );
    }

    /**
     * Filter entries since a given date (inclusive)
     */
    public function filterSince(?DateFilter $since): static
    {
        if (! $since) {
            return $this;
        }

        return $this->filter(fn (SecretHistory $history) => $history->lastModifiedDate() && $history->lastModifiedDate()->gte($since->value())
        );
    }

    /**
     * Filter entries before a given date (inclusive)
     */
    public function filterBefore(?DateFilter $before): static
    {
        if (! $before) {
            return $this;
        }

        return $this->filter(fn (SecretHistory $history) => $history->lastModifiedDate() && $history->lastModifiedDate()->lte($before->value())
        );
    }

    /**
     * Apply multiple filters in sequence
     */
    public function applyFilters(FilterCollection $filters): static
    {
        return $this
            ->filterByUser($filters->get('user'))
            ->filterSince($filters->get('since'))
            ->filterBefore($filters->get('before'));
    }

    /**
     * Apply masking to all values
     */
    public function withMaskedValues(): static
    {
        return $this->map(fn (SecretHistory $history) => $history->withMaskedValue());
    }

    /**
     * Sort by version descending (most recent first)
     */
    public function sortByVersionDesc(): static
    {
        return $this->sortByDesc(fn (SecretHistory $entry) => $entry->version())->values();
    }

    /**
     * Sort by date descending (most recent first)
     */
    public function sortByDateDesc(): static
    {
        return $this->sortByDesc(function (SecretHistory $entry) {
            return $entry->lastModifiedDate()?->timestamp ?? 0;
        })->values();
    }

    /**
     * Take a number of items from the collection while preserving type
     */
    public function take($limit): static
    {
        return new static(parent::take($limit));
    }

    /**
     * Get unique users from the collection
     */
    public function getUniqueUsers(): Collection
    {
        return $this->pluck('lastModifiedUser')
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * Get date range of entries in the collection
     */
    public function getDateRange(): array
    {
        $dates = $this->map(fn (SecretHistory $entry) => $entry->lastModifiedDate())
            ->filter()
            ->sort();

        return [
            'earliest' => $dates->first(),
            'latest' => $dates->last(),
        ];
    }
}

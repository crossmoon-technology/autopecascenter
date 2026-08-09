<?php

namespace App\Services\PartSearch;

use Illuminate\Support\Collection;
use Livewire\Wireable;

/**
 * One page of live-search results — see PartSearchProvider. Manufacturer
 * sites paginate their own results (confirmed live: MTE-Thomson, 12/page),
 * so a single flat Collection isn't enough on its own to know whether more
 * pages exist.
 */
final readonly class PartSearchResultPage implements Wireable
{
    /**
     * @param  Collection<int, PartSearchResult>  $results
     */
    public function __construct(
        public Collection $results,
        public int $currentPage,
        public int $lastPage,
        public ?int $total = null,
    ) {}

    public function hasMultiplePages(): bool
    {
        return $this->lastPage > 1;
    }

    public function toLivewire(): array
    {
        return [
            'results' => $this->results->map(fn (PartSearchResult $result) => $result->toLivewire())->all(),
            'currentPage' => $this->currentPage,
            'lastPage' => $this->lastPage,
            'total' => $this->total,
        ];
    }

    public static function fromLivewire($value): static
    {
        return new self(
            results: collect($value['results'])->map(fn (array $result) => PartSearchResult::fromLivewire($result)),
            currentPage: $value['currentPage'],
            lastPage: $value['lastPage'],
            total: $value['total'],
        );
    }
}

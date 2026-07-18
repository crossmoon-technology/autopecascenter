<?php

namespace App\Services\PartSearch;

use Illuminate\Support\Collection;

interface PartSearchProvider
{
    /**
     * @return Collection<int, PartSearchResult>
     */
    public function search(string $query): Collection;
}

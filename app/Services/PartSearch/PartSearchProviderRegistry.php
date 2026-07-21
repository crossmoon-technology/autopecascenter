<?php

namespace App\Services\PartSearch;

use App\Models\Manufacturer;
use App\Services\PartSearch\Providers\CofapPartSearchProvider;
use App\Services\PartSearch\Providers\HipperFreiosPartSearchProvider;

class PartSearchProviderRegistry
{
    public function for(Manufacturer $manufacturer): ?PartSearchProvider
    {
        return match ($manufacturer->slug) {
            'cofap', 'magneti-marelli' => app(CofapPartSearchProvider::class),
            'hipper-freios' => app(HipperFreiosPartSearchProvider::class),
            default => null,
        };
    }
}

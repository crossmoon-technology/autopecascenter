<?php

namespace App\Filament\Pages\Buscas\Concerns;

use App\Models\Manufacturer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

trait ResolvesPreferredManufacturers
{
    /**
     * Seeds a page's manufacturer chip toggles from the signed-in user's saved
     * preference (see ManufacturerPreferences), restricted to whichever manufacturers
     * are actually eligible on this page. Falls back to selecting every eligible
     * manufacturer when the user has no preference yet, or when none of their
     * preferred manufacturers apply here — otherwise the page would render with
     * nothing selected and immediately demand a manual pick.
     *
     * @param  Collection<int, Manufacturer>  $eligibleManufacturers
     * @return array<int, bool>
     */
    protected function defaultManufacturerSelection(Collection $eligibleManufacturers): array
    {
        $preferred_ids = Auth::user()->preferredManufacturers()->pluck('manufacturers.id')->all();

        $eligibleIds = $eligibleManufacturers->pluck('id');
        $selectedIds = $eligibleIds->intersect($preferred_ids);

        if ($selectedIds->isEmpty()) {
            $selectedIds = $eligibleIds;
        }

        return $eligibleIds
            ->mapWithKeys(fn (int $id) => [$id => $selectedIds->contains($id)])
            ->all();
    }

    /**
     * Restricts a page's manufacturer list to only the ones the signed-in user has
     * enabled (see ManufacturerPreferences) — disabled manufacturers don't even show
     * up as a chip here. Falls back to every eligible manufacturer when the user
     * hasn't enabled any yet, or when none of their enabled manufacturers apply on
     * this page — otherwise the page would render with nothing to pick at all.
     *
     * @param  Collection<int, Manufacturer>  $eligibleManufacturers
     * @return Collection<int, Manufacturer>
     */
    protected function filterToEnabledManufacturers(Collection $eligibleManufacturers): Collection
    {
        $preferred_ids = Auth::user()->preferredManufacturers()->pluck('manufacturers.id')->all();

        $enabled = $eligibleManufacturers->whereIn('id', $preferred_ids)->values();

        return $enabled->isEmpty() ? $eligibleManufacturers : $enabled;
    }
}

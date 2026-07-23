<?php

namespace App\Filament\Client\Pages\Concerns;

use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Restringe fabricantes aos habilitados pelo vendedor que convidou o cliente logado (ver
 * ManufacturerPreferences) — mesma regra de fallback usada nas páginas de busca do
 * vendedor: se ele ainda não habilitou nenhum, mostra todos os ativos em vez de deixar a
 * lista vazia.
 */
trait ScopesManufacturersToInvitingSeller
{
    /**
     * @return Collection<int, Manufacturer>
     */
    protected function manufacturersScopedToInvitingSeller(): Collection
    {
        $eligible = Manufacturer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $seller = $this->invitingSeller();

        if (! $seller) {
            return $eligible;
        }

        $preferred_ids = $seller->preferredManufacturers()->pluck('manufacturers.id')->all();

        if ($preferred_ids === []) {
            return $eligible;
        }

        return $eligible->whereIn('id', $preferred_ids)->values();
    }

    private function invitingSeller(): ?User
    {
        return Auth::user()->invitedBy;
    }
}

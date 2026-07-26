<?php

namespace App\Filament\Client\Pages\Concerns;

use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Restringe fabricantes aos habilitados pelo(s) vendedor(es) do cliente-alvo (ver
 * ManufacturerPreferences) — mesma regra de fallback usada nas páginas de busca do
 * vendedor: se nenhum deles habilitou nada, mostra todos os ativos em vez de deixar a
 * lista vazia.
 *
 * "Cliente-alvo" (ver targetUser(), exigido de quem usa esse trait) é sempre o próprio
 * usuário logado — inclusive pra quem já foi Role::Client e virou Role::Seller (ver
 * User::hasClientHistory()), já que a conta é a mesma, só o role muda.
 *
 * Um cliente pode ter mais de um vendedor (ver User::sellers()) — passar um `$seller`
 * específico escopa pra só esse (ex: ao montar o pedido pra um vendedor escolhido em
 * App\Filament\Client\Pages\CreateOrder); sem passar nada, faz a união das preferências
 * de todos os vendedores do cliente-alvo (ex: navegação geral em
 * App\Filament\Client\Pages\Manufacturers).
 */
trait ScopesManufacturersToInvitingSeller
{
    abstract protected function targetUser(): User;

    /**
     * @return Collection<int, Manufacturer>
     */
    protected function manufacturersScopedToInvitingSeller(?User $seller = null): Collection
    {
        $eligible = Manufacturer::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $sellers = $seller ? collect([$seller]) : $this->targetUser()->sellers;

        if ($sellers->isEmpty()) {
            return $eligible;
        }

        $preferred_ids = $sellers
            ->flatMap(fn (User $seller) => $seller->preferredManufacturers()->pluck('manufacturers.id'))
            ->unique()
            ->values()
            ->all();

        if ($preferred_ids === []) {
            return $eligible;
        }

        return $eligible->whereIn('id', $preferred_ids)->values();
    }
}

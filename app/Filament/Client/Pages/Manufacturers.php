<?php

namespace App\Filament\Client\Pages;

use App\Filament\Client\Pages\Concerns\ScopesManufacturersToInvitingSeller;
use App\Models\Manufacturer;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class Manufacturers extends Page
{
    use ScopesManufacturersToInvitingSeller;

    protected string $view = 'filament.client.pages.manufacturers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::BuildingStorefront;

    protected static ?string $navigationLabel = 'Fabricantes';

    protected static ?string $title = 'Fabricantes';

    /**
     * null = todos os vendedores do cliente (união das preferências) — o padrão.
     */
    public ?int $sellerId = null;

    /**
     * De quem são os vendedores/fabricantes exibidos — sempre o próprio usuário logado.
     * Existe como hook só pra App\Filament\Pages\Cliente\Manufacturers poder reaproveitar
     * esta página inteira sob outro grupo de navegação, pra quem já foi Role::Client e
     * virou Role::Seller (ver User::hasClientHistory()) — o alvo continua a mesma conta.
     */
    protected function targetUser(): User
    {
        return Auth::user();
    }

    /**
     * @return Collection<int, User>
     */
    public function sellers(): Collection
    {
        return $this->targetUser()->sellers()->orderBy('name')->get();
    }

    private function selectedSeller(): ?User
    {
        return $this->sellerId === null ? null : $this->sellers()->firstWhere('id', $this->sellerId);
    }

    /**
     * @return Collection<int, Manufacturer>
     */
    public function getManufacturers(): Collection
    {
        return $this->manufacturersScopedToInvitingSeller($this->selectedSeller());
    }
}

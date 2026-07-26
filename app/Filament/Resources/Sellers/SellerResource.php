<?php

namespace App\Filament\Resources\Sellers;

use App\Enums\Role;
use App\Filament\Resources\Sellers\Pages\EditSeller;
use App\Filament\Resources\Sellers\Pages\ListSellers;
use App\Filament\Resources\Sellers\Schemas\SellerForm;
use App\Filament\Resources\Sellers\Tables\SellersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class SellerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::UserGroup;

    protected static ?string $navigationLabel = 'Vendedores';

    protected static ?string $modelLabel = 'Vendedor';

    protected static ?string $pluralModelLabel = 'Vendedores';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', Role::Seller);
    }

    /**
     * Não existe UserPolicy no projeto (User é usado por vendedor/cliente/super admin,
     * uma policy global desse modelo teria efeito colateral em contextos sem nada a ver
     * com esse resource) — por isso a checagem de acesso fica aqui, restrita a este
     * Resource, em vez de depender de uma Policy que o Filament resolveria sozinho.
     */
    public static function canViewAny(): bool
    {
        return Auth::user()?->role === Role::SuperAdmin;
    }

    public static function canEdit(Model $record): bool
    {
        return Auth::user()?->role === Role::SuperAdmin;
    }

    public static function form(Schema $schema): Schema
    {
        return SellerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SellersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSellers::route('/'),
            'edit' => EditSeller::route('/{record}/edit'),
        ];
    }
}

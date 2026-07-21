<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Buscas\Api;
use App\Filament\Pages\Buscas\CatalogDatabaseSearch;
use App\Filament\Pages\Buscas\Favorites;
use App\Filament\Pages\Buscas\History;
use App\Filament\Pages\Buscas\Iframes;
use App\Filament\Pages\Buscas\Quotations;
use App\Filament\Pages\Buscas\ViewFavoriteList;
use App\Filament\Pages\Buscas\ViewPart;
use App\Filament\Pages\Buscas\ViewQuotation;
use App\Filament\Pages\Configuracoes\GeneralSettings;
use App\Filament\Pages\Configuracoes\ManufacturerPreferences;
use App\Filament\Pages\Configuracoes\NoResultSearches;
use App\Filament\Resources\Catalogs\CatalogResource;
use App\Filament\Resources\Informativos\InformativoResource;
use App\Filament\Resources\Manufacturers\ManufacturerResource;
use App\Filament\Resources\Parts\PartResource;
use App\Http\Middleware\FilamentAuthenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->colors([
                'primary' => Color::hex('#F94603'),
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandName('Auto Peças Center')
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('images/apc-favicon.svg'))
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => Blade::render('<livewire:quotation-cart />'),
            )
            ->navigationGroups([
                'Buscas',
                'Configurações',
            ])
            ->resources([
                ManufacturerResource::class,
                CatalogResource::class,
                PartResource::class,
                InformativoResource::class,
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                Dashboard::class,
                Iframes::class,
                CatalogDatabaseSearch::class,
                Api::class,
                History::class,
                Favorites::class,
                ViewFavoriteList::class,
                Quotations::class,
                ViewQuotation::class,
                ViewPart::class,
                ManufacturerPreferences::class,
                NoResultSearches::class,
                GeneralSettings::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                FilamentAuthenticate::class,
            ]);
    }
}

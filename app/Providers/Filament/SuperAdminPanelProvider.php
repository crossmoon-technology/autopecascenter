<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Ajuda\Faq;
use App\Filament\Pages\Buscas\Api;
use App\Filament\Pages\Buscas\CatalogDatabaseSearch;
use App\Filament\Pages\Buscas\Clients;
use App\Filament\Pages\Buscas\Favorites;
use App\Filament\Pages\Buscas\GuidedQuotation;
use App\Filament\Pages\Buscas\History;
use App\Filament\Pages\Buscas\Iframes;
use App\Filament\Pages\Buscas\Orders;
use App\Filament\Pages\Buscas\Quotations;
use App\Filament\Pages\Buscas\ViewClient;
use App\Filament\Pages\Buscas\ViewFavoriteList;
use App\Filament\Pages\Buscas\ViewPart;
use App\Filament\Pages\Buscas\ViewQuotation;
use App\Filament\Pages\Configuracoes\ApplicationSettings;
use App\Filament\Pages\Configuracoes\GeneralSettings;
use App\Filament\Pages\Configuracoes\ManufacturerPreferences;
use App\Filament\Pages\Configuracoes\NoResultSearches;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Perfil\ViewProfile;
use App\Filament\Resources\Catalogs\CatalogResource;
use App\Filament\Resources\Informativos\InformativoResource;
use App\Filament\Resources\Manufacturers\ManufacturerResource;
use App\Filament\Resources\Parts\PartResource;
use App\Filament\Resources\Sellers\SellerResource;
use App\Filament\Widgets\OrdersOverview;
use App\Http\Middleware\FilamentAuthenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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

class SuperAdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('super-admin')
            ->path('super-admin')
            ->colors([
                'primary' => Color::hex('#F94603'),
            ])
            ->viteTheme('resources/css/filament/super-admin/theme.css')
            ->brandName('Auto Peças Center')
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('favicon.ico'))
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => Blade::render('<livewire:pending-orders-indicator /><livewire:quotation-cart /><livewire:help-menu />'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => Blade::render('<livewire:lgpd-consent /><livewire:onboarding-tutorial />'),
            )
            ->navigationGroups([
                'Buscas',
                'Vendas',
                'Configurações',
            ])
            ->resources([
                ManufacturerResource::class,
                CatalogResource::class,
                PartResource::class,
                InformativoResource::class,
                SellerResource::class,
            ])
            ->discoverResources(in: app_path('Filament/SuperAdmin/Resources'), for: 'App\Filament\SuperAdmin\Resources')
            ->discoverPages(in: app_path('Filament/SuperAdmin/Pages'), for: 'App\Filament\SuperAdmin\Pages')
            ->pages([
                Dashboard::class,
                Faq::class,
                Iframes::class,
                CatalogDatabaseSearch::class,
                Api::class,
                History::class,
                Favorites::class,
                ViewFavoriteList::class,
                Quotations::class,
                ViewQuotation::class,
                Clients::class,
                ViewClient::class,
                Orders::class,
                GuidedQuotation::class,
                ViewPart::class,
                ManufacturerPreferences::class,
                NoResultSearches::class,
                GeneralSettings::class,
                ApplicationSettings::class,
                ViewProfile::class,
            ])
            ->discoverWidgets(in: app_path('Filament/SuperAdmin/Widgets'), for: 'App\Filament\SuperAdmin\Widgets')
            ->widgets([
                OrdersOverview::class,
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

<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Ajuda\Faq;
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
use App\Filament\Pages\Cliente\AddSeller as ClienteAddSeller;
use App\Filament\Pages\Cliente\CreateOrder as ClienteCreateOrder;
use App\Filament\Pages\Cliente\Manufacturers as ClienteManufacturers;
use App\Filament\Pages\Cliente\OrderHistory as ClienteOrderHistory;
use App\Filament\Pages\Configuracoes\GeneralSettings;
use App\Filament\Pages\Configuracoes\ManufacturerPreferences;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Perfil\ViewProfile;
use App\Filament\Resources\Catalogs\CatalogResource;
use App\Filament\Resources\Manufacturers\ManufacturerResource;
use App\Filament\Resources\Parts\PartResource;
use App\Filament\Widgets\OrdersOverview;
use App\Http\Middleware\FilamentAuthenticate;
use App\Http\Middleware\RedirectExpiredSellerTrial;
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

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('vendedor')
            ->colors([
                'primary' => Color::hex('#F94603'),
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
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
                'Cliente',
                'Configurações',
            ])
            ->resources([
                ManufacturerResource::class,
                CatalogResource::class,
                PartResource::class,
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                Dashboard::class,
                Faq::class,
                Iframes::class,
                CatalogDatabaseSearch::class,
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
                GeneralSettings::class,
                ViewProfile::class,
                ClienteOrderHistory::class,
                ClienteCreateOrder::class,
                ClienteManufacturers::class,
                ClienteAddSeller::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
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
                RedirectExpiredSellerTrial::class,
                FilamentAuthenticate::class,
            ]);
    }
}

<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Panel as PanelDeControl;
use App\Filament\Support\Contrasena;
use App\Support\Empresa;
use App\Support\Formato;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
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
            ->login()
            ->passwordReset()
            ->brandName('ServiciosTI')
            ->brandLogo(fn () => view('filament.marca'))
            ->colors([
                'primary' => Color::Amber,
                'sky' => Color::Sky,
                'violet' => Color::Violet,
                'emerald' => Color::Emerald,
                'amber' => Color::Amber,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->globalSearchKeyBindings(['ctrl+k', 'command+k'])
            ->userMenuItems([
                fn () => Contrasena::accionPropia(), // Cambiar contraseña propia (menú arriba a la derecha); se resuelve con el panel ya activo
            ])
            ->navigationGroups([
                NavigationGroup::make('Comercial')->collapsible(false),
                NavigationGroup::make('Catálogo')->collapsible(false),
                NavigationGroup::make('Configuración')->collapsible(false),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                PanelDeControl::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            // Tipo de cambio vigente en la barra superior (enlace a Mi empresa)
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, fn () => Blade::render(
                '<a href="{{ $url }}" title="Tipo de cambio vigente USD → PEN" class="fi-tc-badge" style="display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .6rem;border-radius:.5rem;font-size:.875rem;font-weight:500;font-variant-numeric:tabular-nums;box-shadow:inset 0 0 0 1px rgb(0 0 0 / .1);margin-inline:.5rem">'
                .'<x-filament::icon icon="heroicon-o-arrows-right-left" class="h-4 w-4 text-gray-400" /> TC {{ $tc }}</a>',
                ['url' => \App\Filament\Pages\MiEmpresa::getUrl(), 'tc' => Formato::num(Empresa::tc(), 3)],
            ))
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
                Authenticate::class,
            ]);
    }
}

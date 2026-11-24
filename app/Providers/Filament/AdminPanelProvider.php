<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public const BRONZE = '#9b7346';

    /** Muted bronze palette (hue ~70, low chroma) so it never reads as orange. */
    private const BRONZE_PALETTE = [
        50 => 'oklch(0.975 0.008 75)',
        100 => 'oklch(0.94 0.018 75)',
        200 => 'oklch(0.88 0.035 74)',
        300 => 'oklch(0.80 0.055 73)',
        400 => 'oklch(0.70 0.07 72)',
        500 => 'oklch(0.62 0.075 71)',
        600 => 'oklch(0.56 0.07 70)',
        700 => 'oklch(0.48 0.06 69)',
        800 => 'oklch(0.40 0.05 68)',
        900 => 'oklch(0.33 0.04 67)',
        950 => 'oklch(0.24 0.03 66)',
    ];

    /** Black palette so buttons, toggles and checkboxes come out black. */
    private const INK = [
        50 => 'oklch(0.985 0 0)',
        100 => 'oklch(0.97 0 0)',
        200 => 'oklch(0.922 0 0)',
        300 => 'oklch(0.87 0 0)',
        400 => 'oklch(0.556 0 0)',
        500 => 'oklch(0.371 0 0)',
        600 => 'oklch(0.205 0 0)',
        700 => 'oklch(0.145 0 0)',
        800 => 'oklch(0.1 0 0)',
        900 => 'oklch(0.05 0 0)',
        950 => 'oklch(0 0 0)',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->authGuard('admin')
            ->authPasswordBroker('admins')
            ->login()
            ->profile(isSimple: false)
            ->brandName(config('app.name'))
            ->brandLogo(fn () => file_exists(public_path('images/logo.png')) ? asset('images/logo.png') : null)
            ->brandLogoHeight('3.25rem')
            ->favicon(file_exists(public_path('images/favicon.png')) ? asset('images/favicon.png') : null)
            ->font('Jost')
            // Black & white like the logo, with bronze as the accent colour.
            ->colors([
                'primary' => self::INK,
                'gray' => Color::Neutral,
                'bronze' => self::BRONZE_PALETTE,
                'warning' => self::BRONZE_PALETTE,
            ])
            ->darkMode(false)
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => new HtmlString(<<<'HTML'
                <style>
                    /* Thin bronze line under the top bar and beside the active menu item. */
                    .fi-topbar { border-bottom: 2px solid #9b7346; }
                    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn { box-shadow: inset 3px 0 0 #9b7346; }
                    .fi-simple-main { border-top: 3px solid #9b7346; }
                    /* Overdue pending bills get a bronze marker on the left. */
                    .fi-ta-row.bill-overdue > td:first-child { box-shadow: inset 3px 0 0 #9b7346; }
                </style>
            HTML))
            ->sidebarCollapsibleOnDesktop()
            ->spa()
            ->unsavedChangesAlerts()
            ->navigationGroups([
                NavigationGroup::make('Shop'),
                NavigationGroup::make('Finance'),
                NavigationGroup::make('Access Control'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
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
                Authenticate::class,
            ]);
    }
}

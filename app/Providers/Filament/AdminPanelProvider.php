<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SetCurrentCompany;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
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
            ->colors([
                // Corporate navy-blue — matches the report/PDF letterheads for a
                // consistent brand across screen and print.
                'primary' => Color::hex('#1e40af'),
                'gray' => Color::Slate,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Sky,
            ])
            ->brandName('ERP')
            ->sidebarCollapsibleOnDesktop()
            // Explicit sidebar group order — business-workflow ordered, not alphabetic,
            // so users find things where they expect them.
            ->navigationGroups([
                'Reports',
                'Sales',
                'Export',
                'Purchasing',
                'Inventory',
                'Manufacturing',
                'Accounts',
                'HR',
                'CRM',
                'Documents',
                'Workflow',
                'System',
                'Access',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            // Modern ERP polish stylesheet — hand-written CSS layered over
            // Filament's defaults (cards, KPI stats, tables, sidebar, login). It
            // is a static file (no Vite/theme build), so it can't break the panel;
            // versioned query string busts the browser cache on updates.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<link rel="stylesheet" href="'.asset('css/erp-theme.css').'?v=2">',
            )
            // Floating AI assistant on every panel page — only injected when a
            // Gemini API key is configured. The component is #[Lazy], so it
            // hydrates on first click rather than on every page load.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => filled(config('services.gemini.key'))
                    ? Blade::render('@livewire(\'erp-assistant-chat\')')
                    : '',
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                // Establish the active company from the user's memberships after
                // authentication, before any resource query runs (spec #6).
                // isPersistent: also run on Livewire AJAX updates (table paging,
                // page actions, PDF export) — otherwise CompanyContext would be
                // unset there and company scoping would not be enforced.
                SetCurrentCompany::class,
            ], isPersistent: true);
    }
}

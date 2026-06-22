<?php

namespace App\Providers\Filament;

use App\AvatarProviders\AgricartAvatarProvider;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Settings\Overview as SettingsOverview;
use App\Http\Controllers\ProductCatalog\CategoryImageController;
use App\Support\Navigation\MainMenu;
use Filament\Enums\ThemeMode;
use Filament\Enums\UserMenuPosition;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
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
            ->profile(EditProfile::class, isSimple: false)
            ->brandName(config('agricart.brand.name'))
            ->font('Arial, sans-serif', provider: LocalFontProvider::class)
            ->defaultAvatarProvider(AgricartAvatarProvider::class)
            ->colors([
                'primary' => Color::hex('#83B735'),
                'warning' => Color::hex('#FBBC34'),
            ])
            ->darkMode()
            ->defaultThemeMode(ThemeMode::Light)
            ->breadcrumbs(false)
            ->topbar()
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('15.5rem')
            ->userMenu(position: UserMenuPosition::Topbar)
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label('My Profile')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->url(fn (): string => EditProfile::getUrl()),
                'password' => MenuItem::make()
                    ->label('Change Password')
                    ->icon(Heroicon::OutlinedKey)
                    ->url(fn (): string => EditProfile::getUrl() . '#password'),
                'account-settings' => MenuItem::make()
                    ->label('Account Settings')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->url(fn (): string => SettingsOverview::getUrl()),
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->navigationItems(MainMenu::items())
            ->widgets([])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.layout.partials.mobile-meta')->render(),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.layout.partials.navigation-search-script')->render(),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_LOGO_AFTER,
                fn (): string => view('filament.layout.partials.topbar-breadcrumbs')->render(),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): string => view('filament.layout.partials.topbar-global-search-mobile')->render(),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => view('filament.layout.partials.topbar-notifications')->render(),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => view('filament.layout.partials.topbar-global-search')->render(),
            )
            ->renderHook(
                PanelsRenderHook::CONTENT_BEFORE,
                fn (): string => view('filament.layout.partials.module-nav-bar')->render(),
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
            ])
            ->authenticatedRoutes(function (): void {
                Route::get('category-images', CategoryImageController::class)
                    ->name('product-catalog.category-images');
            });
    }
}

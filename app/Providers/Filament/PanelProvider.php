<?php

namespace App\Providers\Filament;

use App\Enums\CustomizationKey;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Http\Middleware\LanguageMiddleware;
use App\Http\Middleware\PreventRequestForgery;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Http\Middleware\SetSecurityHeaders;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Auth\Pages\EmailVerification\EmailVerificationPrompt;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider as BasePanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

abstract class PanelProvider extends BasePanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // single brand theme shared across admin, app and server panels.
            // the file is compiled by vite into the panels image baked
            // bundle so theme changes survive container recreates without a
            // separate plugin asset publish step.
            ->viteTheme('resources/css/filament/app/theme.css')
            // brand palette registered at source so filament populates every
            // shade and picks accessible shades per component and mode. anchors
            // are the DESIGN_SYSTEM swatches; shades are filament's pick.
            ->colors([
                'primary' => Color::hex('#EE600A'),
                'danger' => Color::hex('#B43C3C'),
                'success' => Color::hex('#2DA76D'),
                'warning' => Color::hex('#D89020'),
                'info' => Color::hex('#5C6878'),
                'gray' => Color::hex('#8B7355'),
            ])
            // overview is SPA-excluded so its per-feature @vite re-fires on every load.
            ->spa(fn () => !request()->routeIs('filament.server.pages.overview'))
            ->spaUrlExceptions([
                '*/oauth/redirect/*',
            ])
            ->databaseNotifications()
            ->brandName(config('app.name', 'Pelican'))
            ->brandLogo(config('app.logo'))
            ->brandLogoHeight('2rem')
            ->favicon(config('app.favicon', '/pelican.ico'))
            ->topNavigation(function () {
                $navigationType = user()?->getCustomization(CustomizationKey::TopNavigation);

                return $navigationType === 'topbar' || $navigationType === true;
            })
            ->topbar(function () {
                $navigationType = user()?->getCustomization(CustomizationKey::TopNavigation);

                return $navigationType === 'topbar' || $navigationType === 'mixed' || $navigationType === true;
            })
            ->maxContentWidth(config('panel.filament.display-width', 'screen-2xl'))
            ->profile(EditProfile::class, false)
            ->userMenuItems([
                'profile' => fn (Action $action) => $action
                    ->url(fn () => EditProfile::getUrl(panel: 'app')),
            ])
            ->login(Login::class)
            ->passwordReset()
            ->emailVerification(EmailVerificationPrompt::class)
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
                EmailAuthentication::make(),
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
                LanguageMiddleware::class,
                SetSecurityHeaders::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                RequireTwoFactorAuthentication::class,
            ]);
    }
}

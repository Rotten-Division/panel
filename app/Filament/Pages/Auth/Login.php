<?php

namespace App\Filament\Pages\Auth;

use App\Extensions\Captcha\CaptchaService;
use App\Extensions\OAuth\OAuthService;
use BladeUI\Icons\Exceptions\SvgNotFound;
use BladeUI\Icons\Factory as IconFactory;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected OAuthService $oauthService;

    protected CaptchaService $captchaService;

    protected IconFactory $iconFactory;

    public function boot(OAuthService $oauthService, CaptchaService $captchaService, IconFactory $iconFactory): void
    {
        $this->oauthService = $oauthService;
        $this->captchaService = $captchaService;
        $this->iconFactory = $iconFactory;
    }

    public function form(Schema $schema): Schema
    {
        if ($this->loginGateClosed()) {
            return $schema->components([
                Placeholder::make('disabled')
                    ->hiddenLabel()
                    ->content(new HtmlString(
                        '<div style="font-size:0.875rem;line-height:1.5;color:rgb(var(--ospite-text-secondary, 156 163 175));">'.
                        e($this->loginGateMessage()).
                        '</div>'
                    )),
            ]);
        }

        $components = [
            $this->getLoginFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getRememberFormComponent(),
            $this->getOAuthFormComponent(),
        ];

        if ($captchaComponent = $this->getCaptchaComponent()) {
            $components[] = $captchaComponent
                ->hidden(fn () => filled($this->userUndertakingMultiFactorAuthentication));
        }

        return $schema
            ->components($components);
    }

    /* defense in depth, the form gate hides the inputs but a direct POST
       could bypass the visual gate. checked again on credential submit. */
    public function authenticate(): ?\Filament\Auth\Http\Responses\Contracts\LoginResponse
    {
        if ($this->loginGateClosed()) {
            throw ValidationException::withMessages([
                'data.login' => $this->loginGateMessage(),
            ]);
        }

        return parent::authenticate();
    }

    /* the onboarding plugin owns the gate. without the plugin installed
       the class is missing and login operates normally. */
    private function loginGateClosed(): bool
    {
        $service = 'RottenDivision\\OspiteOnboarding\\Services\\OnboardingGateService';

        if (!class_exists($service)) {
            return false;
        }

        return app($service)->loginDisabled();
    }

    private function loginGateMessage(): string
    {
        $service = 'RottenDivision\\OspiteOnboarding\\Services\\OnboardingGateService';

        if (!class_exists($service)) {
            return '';
        }

        return app($service)->loginDisabledMessage();
    }

    private function getCaptchaComponent(): ?Component
    {
        return $this->captchaService->getActiveSchema()?->getFormComponent();
    }

    protected function throwFailureValidationException(): never
    {
        $this->dispatch('reset-captcha');

        throw ValidationException::withMessages([
            'data.login' => trans('filament-panels::auth/pages/login.messages.failed')]);
    }

    protected function getLoginFormComponent(): Component
    {
        return TextInput::make('login')
            ->label(trans('filament-panels::auth/pages/login.title'))
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getPasswordFormComponent(): Component
    {
        /** @var TextInput $component */
        $component = parent::getPasswordFormComponent();

        return $component->extraInputAttributes(['tabindex' => 2]);
    }

    protected function getOAuthFormComponent(): Component
    {
        $actions = [];

        $oauthSchemas = $this->oauthService->getEnabled();

        foreach ($oauthSchemas as $schema) {

            $id = $schema->getId();

            $color = $schema->getHexColor();
            $color = is_string($color) ? Color::hex($color) : null;

            $icon = $schema->getIcon();
            if (is_string($icon)) {
                try {
                    $this->iconFactory->svg($icon);
                } catch (SvgNotFound) {
                    $icon = null;
                }
            }

            $actions[] = Action::make("oauth_$id")
                ->label($schema->getName())
                ->icon($icon)
                ->color($color)
                ->url(route('auth.oauth.redirect', ['driver' => $id], false));
        }

        return Actions::make($actions);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $loginType = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        return [
            $loginType => mb_strtolower($data['login']),
            'password' => $data['password'],
        ];
    }
}

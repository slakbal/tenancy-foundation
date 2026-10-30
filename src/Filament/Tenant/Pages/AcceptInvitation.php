<?php

namespace Slakbal\TenancyFoundation\Filament\Tenant\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Panel;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules\Password;
use Slakbal\TenancyFoundation\Actions\Invitation\AcceptInvitation as AcceptInvitationAction;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Exceptions\InvalidInvitation;
use Slakbal\TenancyFoundation\Filament\Components\Schemas\UserNameSchema;

/**
 * @property-read Schema $form
 */
class AcceptInvitation extends SimplePage
{
    protected static ?string $slug = 'invitations/accept/{invitation}';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public $invitation;

    public bool $isExistingUser = false;

    public bool $isAcceptable = true;

    public bool $isOtherAccount = false;

    public static function routeName(): string
    {
        return 'filament.'.FilamentPanel::TENANT->getPanelId().'.invitations.accept';
    }

    public function mount(mixed $invitation): void
    {
        $this->invitation = $invitation instanceof Model
            ? $invitation
            : config('tenancy-foundation.models.invitation')::query()->where('ulid', $invitation)->firstOrFail();
        $this->isAcceptable = is_null($this->invitation->accepted_at) && Carbon::parse($this->invitation->expires_at)->isFuture();
        $this->isExistingUser = config('tenancy-foundation.models.user')::query()->where('email', $this->invitation->email)->exists();

        if ($this->isAcceptable && ! $this->isExistingUser) {
            $this->form->fill();
        }

        if ($this->isAcceptable && $this->isExistingUser) {
            $this->requireInvitedAccount();
        }
    }

    protected function requireInvitedAccount(): void
    {
        $authenticated = Filament::auth()->user();

        if ($authenticated === null) {
            session()->put('url.intended', request()->fullUrl());

            Notification::make()->title(__('Sign in to accept this invitation.'))->info()->send();

            $this->redirect(Filament::getLoginUrl());

            return;
        }

        $this->isOtherAccount = strcasecmp($authenticated->email, $this->invitation->email) !== 0;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components($this->getFormComponents());
    }

    /**
     * @return array<int, Component>
     */
    protected function getFormComponents(): array
    {
        return [
            ...UserNameSchema::components(),
            TextInput::make('password')
                ->label(__('Password'))
                ->password()
                ->revealable()
                ->required()
                ->rule(Password::default())
                ->same('passwordConfirmation'),
            TextInput::make('passwordConfirmation')
                ->label(__('Confirm password'))
                ->password()
                ->revealable()
                ->required()
                ->dehydrated(false),
        ];
    }

    public function acceptAction(): Action
    {
        return Action::make('accept')
            ->label(__('Accept invitation'))
            ->action('accept');
    }

    public function accept(): ?RedirectResponse
    {
        if (! $this->isAcceptable) {
            return null;
        }

        $registrationData = $this->isExistingUser ? [] : $this->form->getState();

        try {
            $user = app(AcceptInvitationAction::class)->handle($this->invitation, $registrationData, Filament::auth()->user());
        } catch (InvalidInvitation $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return null;
        }

        if (! $this->isExistingUser) {
            Filament::auth()->login($user);
        }

        Notification::make()
            ->title(__('Invitation accepted'))
            ->success()
            ->send();

        return redirect()->to(
            appDomainScheme().$this->invitation->tenant->subdomain.'.'.config('tenancy-foundation.domain')
        );
    }

    public function content(Schema $schema): Schema
    {
        if (! $this->isAcceptable || $this->isOtherAccount) {
            return $schema->components([]);
        }

        return $schema->components([
            $this->isExistingUser
                ? $this->getAcceptActionComponent()
                : $this->getFormContentComponent(),
        ]);
    }

    protected function getFormContentComponent(): Component
    {
        return Form::make([
            EmbeddedSchema::make('form'),
        ])
            ->id('form')
            ->livewireSubmitHandler('accept')
            ->footer([
                Actions::make([$this->acceptAction()])->fullWidth(),
            ]);
    }

    protected function getAcceptActionComponent(): Component
    {
        return Actions::make([$this->acceptAction()])->fullWidth();
    }

    public function getTitle(): string|Htmlable
    {
        return __('Accept invitation');
    }

    public function getHeading(): string|Htmlable|null
    {
        if (! $this->isAcceptable) {
            return is_null($this->invitation->accepted_at)
                ? __('This invitation has expired')
                : __('This invitation was already accepted');
        }

        if ($this->isOtherAccount) {
            return __('This invitation is for another account');
        }

        return __('Join :tenant', ['tenant' => $this->invitation->tenant->name]);
    }

    public function getSubHeading(): string|Htmlable|null
    {
        if (! $this->isAcceptable) {
            return is_null($this->invitation->accepted_at)
                ? __('Ask the person who invited you to send a new invitation.')
                : __('You can sign in with your existing account.');
        }

        if ($this->isOtherAccount) {
            return __('Sign out, then open the link again with the invited account :email.', ['email' => $this->invitation->email]);
        }

        return __('You were invited as :email.', ['email' => $this->invitation->email]);
    }

    public static function routes(Panel $panel): void
    {
        Route::get('/'.static::$slug, static::class)
            ->middleware(['signed'])
            ->name('invitations.accept');
    }

    public static function getRoutePath(Panel $panel): string
    {
        return '/'.static::$slug;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return true;
    }
}

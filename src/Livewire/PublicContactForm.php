<?php

namespace Slakbal\TenancyFoundation\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use l3aro\FilamentTurnstile\Facades\FilamentTurnstileFacade;
use l3aro\FilamentTurnstile\Forms\Turnstile;
use Livewire\Component;
use Slakbal\TenancyFoundation\Actions\Contact\CreateContactRequest;
use Slakbal\TenancyFoundation\Enums\ContactSource;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Enums\TenantResolution;

/**
 * @property-read Schema $form
 */
class PublicContactForm extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function boot(): void
    {
        FilamentColor::register(filament()->getPanel(FilamentPanel::TENANT->getPanelId())->getColors());
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components(static::fields());
    }

    /**
     * @return array<int, SchemaComponent>
     */
    protected static function fields(): array
    {
        return collect([
            TextInput::make('name')
                ->label(__('Your full name'))
                ->required()
                ->maxLength(255)
                ->autofocus(),
            TextInput::make('email')
                ->label(__('Email'))
                ->email()
                ->required()
                ->maxLength(255),
            Textarea::make('message')
                ->label(__('Message'))
                ->rows(5)
                ->maxLength(1024)
                ->required(),
            static::hasCaptcha() ? Turnstile::make('captcha')->alignCenter() : null,
        ])->filter()->values()->all();
    }

    protected static function hasCaptcha(): bool
    {
        return class_exists(Turnstile::class);
    }

    protected function resetCaptcha(): void
    {
        if (static::hasCaptcha()) {
            $this->dispatch(FilamentTurnstileFacade::getResetEventName());
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('submit')
                ->footer([
                    Actions::make([$this->getSubmitFormAction()])
                        ->fullWidth()
                        ->key('form-actions'),
                ]),
        ]);
    }

    public function submit(): void
    {
        app(CreateContactRequest::class)->handle($this->form->getState(), ContactSource::PUBLIC, TenantResolution::current()->tenantForHost(request()->getHost()));

        $this->form->fill();

        $this->resetCaptcha();

        Notification::make()
            ->title(__('Thank you! Your message has been sent.'))
            ->success()
            ->send();

        if (filled($url = $this->redirectUrlAfterSubmit())) {
            $this->redirect($url);
        }
    }

    protected function redirectUrlAfterSubmit(): ?string
    {
        return null;
    }

    protected function onValidationError(ValidationException $exception): void
    {
        $this->resetCaptcha();
    }

    protected function getSubmitFormAction(): Action
    {
        return Action::make('submit')
            ->label(__('Submit'))
            ->submit('submit');
    }

    public function render(): View
    {
        return view('tenancy-foundation::livewire.public-contact-form');
    }
}

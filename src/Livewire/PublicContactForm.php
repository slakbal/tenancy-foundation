<?php

namespace Slakbal\TenancyFoundation\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use l3aro\FilamentTurnstile\Facades\FilamentTurnstileFacade;
use l3aro\FilamentTurnstile\Forms\Turnstile;
use Livewire\Component;
use Slakbal\TenancyFoundation\Actions\CreateContactRequest;
use Slakbal\TenancyFoundation\Enums\ContactSource;

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

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
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
                Turnstile::make('captcha')
                    ->alignCenter(),
            ]);
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
        app(CreateContactRequest::class)->handle($this->form->getState(), ContactSource::PUBLIC);

        $this->form->fill();

        $this->dispatch(FilamentTurnstileFacade::getResetEventName());

        Notification::make()
            ->title(__('Thank you! Your message has been sent.'))
            ->success()
            ->send();
    }

    protected function onValidationError(ValidationException $exception): void
    {
        $this->dispatch(FilamentTurnstileFacade::getResetEventName());
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

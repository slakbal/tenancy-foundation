<?php

namespace Slakbal\TenancyFoundation\Filament\Tenant\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Slakbal\TenancyFoundation\Actions\CreateContactRequest;
use Slakbal\TenancyFoundation\Enums\ContactSource;

/**
 * @property-read Schema $form
 */
class ContactUs extends Page
{
    protected static ?string $slug = 'contact';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 100;

    protected Width|string|null $maxContentWidth = Width::ThreeExtraLarge;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('Contact');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Contact us');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        $user = Filament::auth()->user();

        return $schema
            ->statePath('data')
            ->components([
                Textarea::make('message')
                    ->label(__('Message'))
                    ->rows(6)
                    ->maxLength(1024)
                    ->required()
                    ->autofocus()
                    ->helperText(__('Sending as :name - :email', ['name' => $user->name, 'email' => $user->email])),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Get in touch'))
                ->description(__('Send us a message and we will get back to you.'))
                ->schema([
                    Form::make([EmbeddedSchema::make('form')])
                        ->id('form')
                        ->livewireSubmitHandler('submit')
                        ->footer([
                            Actions::make([$this->getSubmitAction()])->key('form-actions'),
                        ]),
                ]),
        ]);
    }

    public function submit(): void
    {
        $user = Filament::auth()->user();

        app(CreateContactRequest::class)->handle([
            'name' => $user->name,
            'email' => $user->email,
            'message' => $this->form->getState()['message'],
        ], ContactSource::TENANT);

        $this->form->fill();

        Notification::make()
            ->title(__('Thank you! Your message has been sent.'))
            ->success()
            ->send();
    }

    protected function getSubmitAction(): Action
    {
        return Action::make('submit')
            ->label(__('Send message'))
            ->submit('submit');
    }
}

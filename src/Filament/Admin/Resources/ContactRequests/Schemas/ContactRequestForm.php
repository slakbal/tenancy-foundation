<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class ContactRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Request'))
                ->afterHeader([
                    Text::make(fn (?Model $record): string => $record?->source->getLabel() ?? '')
                        ->badge()
                        ->color(fn (?Model $record): string => $record?->source->getColor() ?? 'gray'),
                    Text::make(fn (?Model $record): string => $record?->status->getLabel() ?? '')
                        ->badge()
                        ->color(fn (?Model $record): string => $record?->status->getColor() ?? 'gray')
                        ->icon(fn (?Model $record) => $record?->status->getIcon()),
                ])
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label(__('Name'))->disabled(),
                    TextInput::make('email')->label(__('Email'))->disabled(),
                    Textarea::make('message')
                        ->label(__('Message'))
                        ->rows(6)
                        ->disabled()
                        ->columnSpanFull(),
                    Textarea::make('response')
                        ->label(__('Response'))
                        ->helperText(fn (?Model $record): ?string => $record?->canBeRespondedTo() ? __('The response is emailed to the requestor. Only one response can be sent.') : null)
                        ->rows(8)
                        ->maxLength(5000)
                        ->required()
                        ->disabled(fn (?Model $record): bool => ! $record?->canBeRespondedTo())
                        ->columnSpanFull(),
                    TextInput::make('responded_by')
                        ->label(__('Responded by'))
                        ->disabled()
                        ->visible(fn (?Model $record): bool => (bool) $record?->isResponded()),
                    TextInput::make('responded_at')
                        ->label(__('Responded at'))
                        ->formatStateUsing(fn (mixed $state): ?string => displayDateTime($state))
                        ->disabled()
                        ->visible(fn (?Model $record): bool => (bool) $record?->isResponded()),
                ]),
        ]);
    }
}

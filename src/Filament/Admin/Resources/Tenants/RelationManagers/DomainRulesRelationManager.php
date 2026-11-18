<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\RelationManagers;

use Closure;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Enums\DomainRuleType;

class DomainRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'domainRules';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Access rules');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->domainRules()->count();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('value')
                ->label(__('Domain or email address'))
                ->helperText(__('A domain like example.com, or a full email address. A rule for an address wins over the rule for its domain.'))
                ->required()
                ->maxLength(255)
                ->regex('/^\s*([^@\s]+@)?([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z0-9-]{2,}\s*$/i')
                ->validationMessages(['regex' => __('Enter a domain like example.com or a full email address.')])
                ->rule(fn (?Model $record): Closure => $this->uniqueValueRule($record)),
            Select::make('type')
                ->label(__('Type'))
                ->options(DomainRuleType::class)
                ->default(DomainRuleType::BLOCK)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('value')
            ->modelLabel(__('Access rule'))
            ->pluralModelLabel(__('Access rules'))
            ->defaultSort('value')
            ->columns([
                TextColumn::make('value')
                    ->label(__('Domain or email address'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('Type'))
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options(DomainRuleType::class),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }

    // The value is stored lower case and trimmed, so it is compared that way.
    protected function uniqueValueRule(?Model $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            $exists = $this->getOwnerRecord()->domainRules()
                ->where('value', Str::lower(trim((string) $value)))
                ->when($record, fn (Builder $query): Builder => $query->whereKeyNot($record->getKey()))
                ->exists();

            if ($exists) {
                $fail(__('This tenant already has a rule for this domain or email address.'));
            }
        };
    }
}

<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Schemas;

use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Enums\DomainRuleType;

class DomainRuleForm
{
    public static function configure(Schema $schema, Model $tenant): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('value')
                ->label(__('Domain or email address'))
                ->helperText(__('A domain like example.com, or a full email address. A rule for an address wins over the rule for its domain.'))
                ->required()
                ->maxLength(255)
                ->regex('/^\s*([^@\s]+@)?([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z0-9-]{2,}\s*$/i')
                ->validationMessages(['regex' => __('Enter a domain like example.com or a full email address.')])
                ->rule(fn (?Model $record): Closure => static::uniqueValueRule($tenant, $record)),
            Select::make('type')
                ->label(__('Type'))
                ->options(DomainRuleType::class)
                ->default(DomainRuleType::BLOCK)
                ->required(),
        ]);
    }

    // The value is stored lower case and trimmed, so it is compared that way.
    protected static function uniqueValueRule(Model $tenant, ?Model $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($tenant, $record): void {
            $exists = $tenant->domainRules()
                ->where('value', Str::lower(trim((string) $value)))
                ->when($record, fn (Builder $query): Builder => $query->whereKeyNot($record->getKey()))
                ->exists();

            if ($exists) {
                $fail(__('This tenant already has a rule for this domain or email address.'));
            }
        };
    }
}

<?php

namespace Slakbal\TenancyFoundation\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile as BaseEditTenantProfile;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;
use Slakbal\TenancyFoundation\Filament\Components\Schemas\TenantDetailsSchema;

class EditTenantProfile extends BaseEditTenantProfile
{
    protected function getCopyUrlAction(): Action
    {
        return Action::make('copyUrl')
            ->icon(Heroicon::OutlinedClipboardDocument)
            ->tooltip(__('Copy URL'))
            ->alpineClickHandler(fn (): string => 'window.navigator.clipboard.writeText('.Js::from(TenantDetailsSchema::url($this->tenant->subdomain)).'); $tooltip('.Js::from(__('Copied')).', { timeout: 1500 })');
    }

    public static function getLabel(): string
    {
        return __('Tenant profile');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->getTenantProfileFormComponents());
    }

    /**
     * @return array<int, Component>
     */
    protected function getTenantProfileFormComponents(): array
    {
        return [
            Section::make(__('Tenant details'))
                ->description(__('The name and web address of your tenant.'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('Tenant name'))
                        ->required()
                        ->maxLength(50),
                    TenantDetailsSchema::subdomain(ignoreRecord: true)
                        ->readOnly()
                        ->dehydrated(false)
                        ->suffixAction($this->getCopyUrlAction()),
                ]),
        ];
    }
}

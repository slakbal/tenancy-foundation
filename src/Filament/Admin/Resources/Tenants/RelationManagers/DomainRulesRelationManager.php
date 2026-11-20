<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Schemas\DomainRuleForm;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Tables\DomainRulesTable;

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
        return DomainRuleForm::configure($schema, $this->getOwnerRecord());
    }

    public function table(Table $table): Table
    {
        return DomainRulesTable::configure($table);
    }
}

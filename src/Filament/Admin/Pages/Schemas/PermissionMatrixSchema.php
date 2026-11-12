<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Pages\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Filament\Admin\Pages\ManageRolePermissions;
use Slakbal\TenancyFoundation\Support\Permissions\PermissionLabels;
use Slakbal\TenancyFoundation\Support\Permissions\PermissionMatrix;

/**
 * Builds the permission matrix: one tab per guard, one collapsible section per resource, one checkbox per
 * permission and role.
 */
class PermissionMatrixSchema
{
    /**
     * @param  array<int, PermissionMatrix>  $matrices
     */
    public static function make(array $matrices): Tabs
    {
        return Tabs::make('guards')
            ->tabs(array_map(fn (PermissionMatrix $matrix): Tab => static::tab($matrix), $matrices));
    }

    protected static function tab(PermissionMatrix $matrix): Tab
    {
        return Tab::make($matrix->panel->value)
            ->label(static::tabLabel($matrix->panel))
            ->icon(static::tabIcon($matrix->panel))
            ->schema([
                ...static::missingNotice($matrix),
                ...collect($matrix->groups)
                    ->map(fn (array $permissions): Section => static::groupSection($matrix, $permissions))
                    ->values()
                    ->all(),
                ...static::unknownSection($matrix),
            ]);
    }

    public static function tabLabel(FilamentPanel $panel): string
    {
        return match ($panel) {
            FilamentPanel::ADMIN => __('Admin team'),
            FilamentPanel::TENANT => __('Tenant users'),
        };
    }

    protected static function tabIcon(FilamentPanel $panel): Heroicon
    {
        return match ($panel) {
            FilamentPanel::ADMIN => Heroicon::OutlinedShieldCheck,
            FilamentPanel::TENANT => Heroicon::OutlinedUserGroup,
        };
    }

    /**
     * @return array<int, Component>
     */
    protected static function missingNotice(PermissionMatrix $matrix): array
    {
        if ($matrix->missing === []) {
            return [];
        }

        return [
            Callout::make(__('Some permissions have not been created yet'))
                ->description(__('Run php artisan tenancy-foundation:sync-permissions to create them, then reload this page: :permissions', [
                    'permissions' => PermissionLabels::fullList($matrix->missing),
                ]))
                ->warning(),
        ];
    }

    /**
     * @param  array<int, Model>  $permissions
     */
    protected static function groupSection(PermissionMatrix $matrix, array $permissions): Section
    {
        $columns = 2 + $matrix->roles->count();

        return Section::make(PermissionLabels::group($permissions[0]->name))
            ->description(trans_choice(':count permission|:count permissions', count($permissions)))
            ->collapsible()
            ->collapsed()
            ->compact()
            ->schema([
                Grid::make($columns)->schema([
                    Text::make(__('Permission'))->weight(FontWeight::Bold)->columnSpan(2),
                    ...$matrix->roles->map(fn (Model $role): Component => static::roleHeader($matrix, $role, $permissions))->all(),
                    ...collect($permissions)->flatMap(fn (Model $permission): array => static::permissionRow($matrix, $permission))->all(),
                ]),
            ]);
    }

    /**
     * @param  array<int, Model>  $permissions
     */
    protected static function roleHeader(PermissionMatrix $matrix, Model $role, array $permissions): Component
    {
        if ($matrix->isLocked($role)) {
            return Text::make(PermissionLabels::role($role->name))
                ->weight(FontWeight::Bold)
                ->icon(Heroicon::LockClosed)
                ->tooltip(__('Full access, cannot be changed'));
        }

        return Group::make([
            Text::make(PermissionLabels::role($role->name))->weight(FontWeight::Bold),
            Actions::make([static::toggleAction($matrix, $role, $permissions)]),
        ]);
    }

    /**
     * @param  array<int, Model>  $permissions
     */
    protected static function toggleAction(PermissionMatrix $matrix, Model $role, array $permissions): Action
    {
        $panel = $matrix->panel->value;
        $ids = array_map(fn (Model $permission): int|string => $permission->getKey(), $permissions);

        return Action::make('toggle_'.$panel.'_'.PermissionLabels::groupKey($permissions[0]->name).'_'.$role->getKey())
            ->label(fn (ManageRolePermissions $livewire): string => $livewire->isGroupChecked($panel, $role->getKey(), $ids) ? __('Select none') : __('Select all'))
            ->link()
            ->size(Size::Small)
            ->action(fn (ManageRolePermissions $livewire) => $livewire->toggleGroup($panel, $role->getKey(), $ids));
    }

    /**
     * @return array<int, Component>
     */
    protected static function permissionRow(PermissionMatrix $matrix, Model $permission): array
    {
        return [
            Text::make(PermissionLabels::action($permission->name))->columnSpan(2),
            ...$matrix->roles->map(fn (Model $role): Component => $matrix->isLocked($role)
                ? static::lockedCell()
                : static::checkbox($matrix, $role, $permission))->all(),
        ];
    }

    protected static function lockedCell(): Icon
    {
        return Icon::make(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->tooltip(__('Full access, cannot be changed'));
    }

    protected static function checkbox(PermissionMatrix $matrix, Model $role, Model $permission): Checkbox
    {
        return Checkbox::make($matrix->panel->value.'.'.$role->getKey().'.'.$permission->getKey())
            ->label(PermissionLabels::role($role->name).': '.PermissionLabels::full($permission->name))
            ->hiddenLabel()
            ->live();
    }

    /**
     * @return array<int, Component>
     */
    protected static function unknownSection(PermissionMatrix $matrix): array
    {
        if ($matrix->unknown === []) {
            return [];
        }

        return [
            Section::make(__('Unknown permissions'))
                ->description(__('These permissions exist in the database but not in the code. They are shown for information and cannot be changed here.'))
                ->collapsible()
                ->collapsed()
                ->compact()
                ->schema([
                    Grid::make(2 + $matrix->roles->count())->schema([
                        Text::make(__('Permission'))->weight(FontWeight::Bold)->columnSpan(2),
                        ...$matrix->roles->map(fn (Model $role): Text => Text::make(PermissionLabels::role($role->name))->weight(FontWeight::Bold))->all(),
                        ...collect($matrix->unknown)->flatMap(fn (string $name): array => static::unknownRow($matrix, $name))->all(),
                    ]),
                ]),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function unknownRow(PermissionMatrix $matrix, string $name): array
    {
        return [
            Text::make($name)->columnSpan(2),
            ...$matrix->roles->map(fn (Model $role): Component => Icon::make(in_array($name, $matrix->heldBy($role), true) ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedMinusCircle)
                ->color(in_array($name, $matrix->heldBy($role), true) ? 'success' : 'gray'))->all(),
        ];
    }
}

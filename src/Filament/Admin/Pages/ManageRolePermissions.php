<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Slakbal\TenancyFoundation\Actions\Roles\ApplyPermissionMatrix;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Filament\Admin\Pages\Schemas\PermissionMatrixSchema;
use Slakbal\TenancyFoundation\Support\Permissions\PermissionChangeSet;
use Slakbal\TenancyFoundation\Support\Permissions\PermissionLabels;
use Slakbal\TenancyFoundation\Support\Permissions\PermissionMatrix;
use Slakbal\TenancyFoundation\Support\Permissions\PermissionMatrixLoader;
use Slakbal\TenancyFoundation\Support\Permissions\RoleChange;
use UnitEnum;

/**
 * Edit which role holds which permission, for every tenant at once. Saving goes through SyncRolePermissions.
 */
class ManageRolePermissions extends Page
{
    protected static ?string $slug = 'role-permissions';

    protected static ?int $navigationSort = 90;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * Checkbox state: panel => role id => permission id => held.
     *
     * @var array<string, array<int|string, array<int|string, bool>>>
     */
    public array $data = [];

    /**
     * The state as last loaded from the database, to tell what is unsaved.
     *
     * @var array<string, array<int|string, array<int|string, bool>>>
     */
    public array $original = [];

    /**
     * @var array<string, PermissionMatrix>|null
     */
    protected ?array $matrices = null;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can(AdminPermissions::PERMISSIONS_MANAGE) ?? false;
    }

    /**
     * @return array<int, FilamentPanel>
     */
    protected static function panels(): array
    {
        return [FilamentPanel::ADMIN, FilamentPanel::TENANT];
    }

    protected static function matrixLoader(): PermissionMatrixLoader
    {
        return app(PermissionMatrixLoader::class);
    }

    /**
     * @param  array<int, PermissionMatrix>  $matrices
     */
    protected static function matrixSchema(array $matrices): Component
    {
        return PermissionMatrixSchema::make($matrices);
    }

    public static function getNavigationLabel(): string
    {
        return __('Role permissions');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Security');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Role permissions');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Choose what each role may do. Changes apply to every tenant and take effect immediately after you save.');
    }

    public function mount(): void
    {
        $this->resetChanges();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Text::make(fn (): string => trans_choice(':count unsaved change|:count unsaved changes', $this->changeCount()))
                    ->badge()
                    ->color('warning')
                    ->visible(fn (): bool => $this->hasChanges()),
                static::matrixSchema(array_values($this->matrices())),
            ]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [$this->resetAction(), $this->saveAction()];
    }

    protected function resetAction(): Action
    {
        return Action::make('reset')
            ->label(__('Reset'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->disabled(fn (): bool => ! $this->hasChanges())
            ->action(fn () => $this->resetChanges());
    }

    protected function saveAction(): Action
    {
        return Action::make('save')
            ->label(__('Save changes'))
            ->icon(Heroicon::OutlinedCheck)
            ->disabled(fn (): bool => ! $this->hasChanges())
            ->modalHeading(__('Save permission changes?'))
            ->modalSubmitActionLabel(__('Save changes'))
            ->schema(fn (): array => $this->confirmationComponents())
            ->action(fn () => $this->save());
    }

    /**
     * @return array<int, Component>
     */
    protected function confirmationComponents(): array
    {
        return [
            Callout::make(__('Applies to every tenant'))
                ->description(__('These changes affect all users holding these roles, in every tenant, as soon as you save.'))
                ->warning(),
            ...array_map(fn (RoleChange $change): Section => $this->changeSection($change), $this->pendingChanges()),
        ];
    }

    protected function changeSection(RoleChange $change): Section
    {
        $lines = [];

        if ($change->added !== []) {
            $lines[] = Text::make(__('Gains: :permissions', ['permissions' => PermissionLabels::fullList($change->added)]))->color('success');
        }

        if ($change->removed !== []) {
            $lines[] = Text::make(__('Loses: :permissions', ['permissions' => PermissionLabels::fullList($change->removed)]))->color('danger');
        }

        return Section::make($this->changeHeading($change))->compact()->schema($lines);
    }

    protected function changeHeading(RoleChange $change): string
    {
        return PermissionLabels::role($change->role->name).' ('.PermissionMatrixSchema::tabLabel($change->panel).')';
    }

    public function save(): void
    {
        $actor = Filament::auth()->user();
        $applied = [];

        foreach (static::panels() as $panel) {
            $result = app(ApplyPermissionMatrix::class)->handle($this->freshMatrix($panel), $this->data[$panel->value] ?? [], $actor);

            $applied = [...$applied, ...$result['applied']];
            $this->notifyFailures($result['failed']);
        }

        $this->notifySaved($applied);
        $this->resetChanges();
    }

    /**
     * @param  array<int, RoleChange>  $applied
     */
    protected function notifySaved(array $applied): void
    {
        if ($applied === []) {
            Notification::make()->title(__('Nothing changed'))->body(__('The permissions already matched what you selected.'))->info()->send();

            return;
        }

        Notification::make()
            ->title(trans_choice('Permissions saved for :count role|Permissions saved for :count roles', count($applied)))
            ->body(implode(', ', array_map(fn (RoleChange $change): string => $this->changeHeading($change), $applied)))
            ->success()
            ->send();
    }

    /**
     * @param  array<string, string>  $failed
     */
    protected function notifyFailures(array $failed): void
    {
        foreach ($failed as $role => $message) {
            Notification::make()
                ->title(__('Could not save the :role role', ['role' => PermissionLabels::role($role)]))
                ->body($message)
                ->danger()
                ->send();
        }
    }

    public function resetChanges(): void
    {
        $this->matrices = null;
        $this->data = $this->original = collect($this->matrices())->map(fn (PermissionMatrix $matrix): array => $matrix->state())->all();
    }

    public function hasChanges(): bool
    {
        return $this->changeCount() > 0;
    }

    public function changeCount(): int
    {
        return PermissionChangeSet::countDifferences($this->flatten($this->original), $this->flatten($this->data));
    }

    /**
     * @param  array<int|string, int|string>  $permissionIds
     */
    public function isGroupChecked(string $panel, int|string $roleId, array $permissionIds): bool
    {
        return collect($permissionIds)->every(fn (int|string $id): bool => (bool) ($this->data[$panel][$roleId][$id] ?? false));
    }

    /**
     * @param  array<int|string, int|string>  $permissionIds
     */
    public function toggleGroup(string $panel, int|string $roleId, array $permissionIds): void
    {
        $checked = ! $this->isGroupChecked($panel, $roleId, $permissionIds);

        foreach ($permissionIds as $id) {
            $this->data[$panel][$roleId][$id] = $checked;
        }
    }

    /**
     * @return array<int, RoleChange>
     */
    protected function pendingChanges(): array
    {
        return collect(static::panels())
            ->flatMap(fn (FilamentPanel $panel): array => PermissionChangeSet::between($this->freshMatrix($panel), $this->data[$panel->value] ?? []))
            ->all();
    }

    /**
     * @return array<string, PermissionMatrix>
     */
    protected function matrices(): array
    {
        return $this->matrices ??= collect(static::panels())
            ->mapWithKeys(fn (FilamentPanel $panel): array => [$panel->value => $this->freshMatrix($panel)])
            ->all();
    }

    protected function freshMatrix(FilamentPanel $panel): PermissionMatrix
    {
        return static::matrixLoader()->load($panel);
    }

    /**
     * Flattens panel => role => permission cells into one comparable map.
     *
     * @param  array<string, array<int|string, array<int|string, bool>>>  $state
     * @return array<string, array<string, bool>>
     */
    protected function flatten(array $state): array
    {
        $flat = [];

        foreach ($state as $panel => $roles) {
            foreach ($roles as $roleId => $cells) {
                $flat[$panel.'.'.$roleId] = $cells;
            }
        }

        return $flat;
    }
}

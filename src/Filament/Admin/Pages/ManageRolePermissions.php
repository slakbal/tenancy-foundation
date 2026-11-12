<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Locked;
use Slakbal\TenancyFoundation\Actions\Roles\ApplyPermissionMatrix;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Filament\Admin\Pages\Schemas\PermissionMatrixSchema;
use Slakbal\TenancyFoundation\Support\Permissions\PermissionChangeSet;
use Slakbal\TenancyFoundation\Support\Permissions\PermissionLabels;
use Slakbal\TenancyFoundation\Support\Permissions\PermissionMatrix;
use Slakbal\TenancyFoundation\Support\Permissions\PermissionMatrixLoader;
use Slakbal\TenancyFoundation\Support\Permissions\PermissionSaveNotifications;
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
     * The guard tab being shown. Only the matrix of a tab that was opened is loaded, see loadPanel().
     */
    public string $activeTab = '';

    /**
     * The state as last loaded from the database, to tell what is unsaved. Its keys are the loaded panels, so a
     * client cannot mark a panel as loaded.
     *
     * @var array<string, array<int|string, array<int|string, bool>>>
     */
    #[Locked]
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
     * @param  array<int, FilamentPanel>  $panels
     * @param  array<string, PermissionMatrix>  $matrices  the loaded matrices by panel; other tabs stay empty
     */
    protected static function matrixSchema(array $panels, array $matrices): Component
    {
        return PermissionMatrixSchema::make($panels, $matrices);
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
        $this->activeTab = static::panels()[0]->value;
        $this->loadPanel(static::panels()[0]);
    }

    /**
     * Loads a tab's matrix the first time it is opened. This runs before the property changes (and before Filament
     * caches the content schema for the request), and a tab that is already loaded keeps its unsaved edits.
     */
    public function updatingActiveTab(mixed $value): void
    {
        $panel = is_string($value) ? $this->panelFor($value) : null;

        if ($panel !== null && ! array_key_exists($panel->value, $this->original)) {
            $this->loadPanel($panel);
        }
    }

    public function updatedActiveTab(): void
    {
        $this->activeTab = ($this->panelFor($this->activeTab) ?? static::panels()[0])->value;
    }

    protected function loadPanel(FilamentPanel $panel): void
    {
        $matrices = $this->matrices();
        $matrix = $this->freshMatrix($panel);

        $this->matrices = [...$matrices, $panel->value => $matrix];
        $this->data[$panel->value] = $this->original[$panel->value] = $matrix->state();
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
                static::matrixSchema(static::panels(), $this->matrices()),
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

        return Section::make(PermissionSaveNotifications::heading($change))->compact()->schema($lines);
    }

    public function save(): void
    {
        $actor = Filament::auth()->user();
        $applied = [];

        foreach ($this->loadedPanels() as $panel) {
            $result = app(ApplyPermissionMatrix::class)->handle($this->freshMatrix($panel), $this->data[$panel->value] ?? [], $actor);

            $applied = [...$applied, ...$result['applied']];
            PermissionSaveNotifications::failed($result['failed']);
        }

        PermissionSaveNotifications::saved($applied);
        $this->resetChanges();
    }

    /**
     * Reloads every loaded tab from the database. State sent for any other panel is dropped.
     */
    public function resetChanges(): void
    {
        $panels = $this->loadedPanels();

        $this->matrices = [];
        $this->data = $this->original = [];

        foreach ($panels as $panel) {
            $this->loadPanel($panel);
        }
    }

    /**
     * @return array<int, FilamentPanel>
     */
    protected function loadedPanels(): array
    {
        return array_values(array_filter(static::panels(), fn (FilamentPanel $panel): bool => array_key_exists($panel->value, $this->original)));
    }

    protected function panelFor(string $value): ?FilamentPanel
    {
        return collect(static::panels())->first(fn (FilamentPanel $panel): bool => $panel->value === $value);
    }

    public function hasChanges(): bool
    {
        return $this->changeCount() > 0;
    }

    public function changeCount(): int
    {
        return PermissionChangeSet::countDifferences($this->flatten($this->original), $this->flatten(array_intersect_key($this->data, $this->original)));
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
        return collect($this->loadedPanels())
            ->flatMap(fn (FilamentPanel $panel): array => PermissionChangeSet::between($this->freshMatrix($panel), $this->data[$panel->value] ?? []))
            ->all();
    }

    /**
     * The matrices of the loaded tabs only.
     *
     * @return array<string, PermissionMatrix>
     */
    protected function matrices(): array
    {
        return $this->matrices ??= collect($this->loadedPanels())
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

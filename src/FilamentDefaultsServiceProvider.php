<?php

namespace Slakbal\TenancyFoundation;

use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications;
use Filament\Pages;
use Filament\Panel;
use Filament\Schemas;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;

class FilamentDefaultsServiceProvider extends ServiceProvider
{
    protected ?bool $translateLabels = null;

    protected ?bool $actionDefaults = null;

    protected ?bool $selectDefaults = null;

    protected ?bool $datetimeDefaults = null;

    protected ?bool $fileUploadDefaults = null;

    protected ?bool $repeaterDefaults = null;

    protected ?bool $formDefaults = null;

    protected ?bool $pageDefaults = null;

    protected ?bool $tableDefaults = null;

    protected ?bool $formatDefaults = null;

    protected ?bool $panelDefaults = null;

    public function register(): void
    {
        if (config('filament-defaults.enabled', true)) {
            $this->apply();
        }
    }

    public function boot(): void {}

    protected function isEnabled(?bool $override, string $key): bool
    {
        return $override ?? (bool) config("filament-defaults.{$key}", true);
    }

    protected function isItemEnabled(string $group, string $item): bool
    {
        return (bool) config("filament-defaults.{$group}.{$item}", true);
    }

    public function apply(): void
    {
        if ($this->isEnabled($this->translateLabels, 'translate_labels')) {
            $this->applyTranslateLabels();
        }

        if ($this->isEnabled($this->actionDefaults, 'action_defaults')) {
            $this->applyActionDefaults();
        }

        if ($this->isEnabled($this->selectDefaults, 'select_defaults')) {
            $this->applySelectDefaults();
        }

        if ($this->isEnabled($this->datetimeDefaults, 'datetime_defaults')) {
            $this->applyDateTimeDefaults();
        }

        if ($this->isEnabled($this->fileUploadDefaults, 'fileupload_defaults')) {
            $this->applyFileUploadDefaults();
        }

        if ($this->isEnabled($this->repeaterDefaults, 'repeater_defaults')) {
            $this->applyRepeaterDefaults();
        }

        if ($this->isEnabled($this->formDefaults, 'form_defaults')) {
            $this->applyFormDefaults();
        }

        if ($this->isEnabled($this->pageDefaults, 'page_defaults')) {
            $this->applyPageDefaults();
        }

        if ($this->isEnabled($this->tableDefaults, 'table_defaults')) {
            $this->applyTableDefaults();
        }

        if ($this->isEnabled($this->formatDefaults, 'format_defaults')) {
            $this->applyFormatDefaults();
        }

        if ($this->isEnabled($this->panelDefaults, 'panel_defaults')) {
            $this->applyPanelDefaults();
        }

    }

    protected function applyTranslateLabels(): void
    {
        Forms\Components\Field::configureUsing(fn (Forms\Components\Field $field) => $field
            ->translateLabel()
        );

        Infolists\Components\Entry::configureUsing(fn (Infolists\Components\Entry $entry) => $entry
            ->translateLabel()
        );

        Tables\Columns\Column::configureUsing(fn (Tables\Columns\Column $column) => $column
            ->translateLabel()
        );
    }

    protected function applyActionDefaults(): void
    {
        if ($this->isItemEnabled('actions', 'action_group')) {
            Actions\ActionGroup::configureUsing(fn (Actions\ActionGroup $action) => $action
                ->icon(Heroicon::EllipsisVertical)
            );
        }

        if ($this->isItemEnabled('actions', 'action')) {
            Actions\Action::configureUsing(fn (Actions\Action $action) => $action
                ->translateLabel()
                ->modalWidth(Width::Medium)
                ->closeModalByClickingAway(false)
            );
        }

        if ($this->isItemEnabled('actions', 'create_action')) {
            Actions\CreateAction::configureUsing(fn (Actions\CreateAction $action) => $action
                ->icon(Heroicon::OutlinedPlus)
                    // ->hiddenLabel()
                ->createAnother(false)
            );
        }

        if ($this->isItemEnabled('actions', 'edit_action')) {
            Actions\EditAction::configureUsing(fn (Actions\EditAction $action) => $action
                ->icon(Heroicon::OutlinedPencilSquare)
                ->hiddenLabel()
                ->iconButton()
            );
        }

        if ($this->isItemEnabled('actions', 'delete_action')) {
            Actions\DeleteAction::configureUsing(fn (Actions\DeleteAction $action) => $action
                ->icon(Heroicon::OutlinedTrash)
                ->hiddenLabel()
                ->iconButton()
            );
        }

        if ($this->isItemEnabled('actions', 'view_action')) {
            Actions\ViewAction::configureUsing(fn (Actions\ViewAction $action) => $action
                ->icon(Heroicon::OutlinedEye)
                ->hiddenLabel()
                ->iconButton()
            );
        }

        if ($this->isItemEnabled('actions', 'restore_action')) {
            Actions\RestoreAction::configureUsing(fn (Actions\RestoreAction $action) => $action
                ->icon(Heroicon::ArrowUturnLeft)
                ->hiddenLabel()
                ->iconButton()
            );
        }

        if ($this->isItemEnabled('actions', 'force_delete_action')) {
            Actions\ForceDeleteAction::configureUsing(fn (Actions\ForceDeleteAction $action) => $action
                ->icon(Heroicon::OutlinedTrash)
                ->hiddenLabel()
                ->iconButton()
            );
        }

        if ($this->isItemEnabled('actions', 'bulk_action_group')) {
            Actions\BulkActionGroup::configureUsing(fn (Actions\BulkActionGroup $group) => $group
                //
            );
        }

        if ($this->isItemEnabled('actions', 'delete_bulk_action')) {
            Actions\DeleteBulkAction::configureUsing(fn (Actions\DeleteBulkAction $action) => $action
                ->icon(Heroicon::OutlinedTrash)
            );
        }

        if ($this->isItemEnabled('actions', 'force_delete_bulk_action')) {
            Actions\ForceDeleteBulkAction::configureUsing(fn (Actions\ForceDeleteBulkAction $action) => $action
                ->icon(Heroicon::OutlinedTrash)
            );
        }

        if ($this->isItemEnabled('actions', 'restore_bulk_action')) {
            Actions\RestoreBulkAction::configureUsing(fn (Actions\RestoreBulkAction $action) => $action
                ->icon(Heroicon::ArrowUturnLeft)
            );
        }
    }

    protected function applySelectDefaults(): void
    {
        Forms\Components\Select::configureUsing(function (Forms\Components\Select $component) {

            if ($this->isItemEnabled('select', 'native')) {
                $component->native(false);
            }

            if ($this->isItemEnabled('select', 'selectable_placeholder')) {
                $component->selectablePlaceholder(function (Forms\Components\Select $component) {
                    return ! $component->isRequired();
                });
            }

            if ($this->isItemEnabled('select', 'searchable')) {
                $component->searchable(fn (Forms\Components\Select $component) => $component
                    ->hasRelationship()
                );
            }

            if ($this->isItemEnabled('select', 'preload')) {
                $component->preload(fn (Forms\Components\Select $component) => $component
                    ->isSearchable()
                );
            }

            return $component;
        });
    }

    protected function applyDateTimeDefaults(): void
    {
        Forms\Components\DateTimePicker::configureUsing(function (Forms\Components\DateTimePicker $component) {

            if ($this->isItemEnabled('datetime', 'seconds')) {
                $component->seconds(false);
            }

            if ($this->isItemEnabled('datetime', 'max_date')) {
                $component->maxDate('9999-12-31T23:59');
            }

            return $component;
        });
    }

    protected function applyFileUploadDefaults(): void
    {
        Forms\Components\FileUpload::configureUsing(fn (Forms\Components\FileUpload $component) => $component
            ->moveFiles()
        );
    }

    protected function applyRepeaterDefaults(): void
    {
        if ($this->isItemEnabled('repeater', 'repeater')) {
            Forms\Components\Repeater::configureUsing(fn (Forms\Components\Repeater $component) => $component
                ->deleteAction(fn (Actions\Action $action) => $action
                    ->requiresConfirmation())
            );
        }

        if ($this->isItemEnabled('repeater', 'builder')) {
            Forms\Components\Builder::configureUsing(fn (Forms\Components\Builder $component) => $component
                ->deleteAction(fn (Actions\Action $action) => $action
                    ->requiresConfirmation())
            );
        }
    }

    protected function applyFormDefaults(): void
    {
        if ($this->isItemEnabled('form', 'toggle_buttons')) {
            Forms\Components\ToggleButtons::configureUsing(fn (Forms\Components\ToggleButtons $component) => $component
                ->inline()
                ->grouped()
            );
        }

        if ($this->isItemEnabled('form', 'text_input')) {
            Forms\Components\TextInput::configureUsing(fn (Forms\Components\TextInput $component) => $component
                ->minValue(0)
            );
        }

        if ($this->isItemEnabled('form', 'textarea')) {
            Forms\Components\Textarea::configureUsing(fn (Forms\Components\Textarea $component) => $component
                ->rows(4)
            );
        }
    }

    protected function applyPageDefaults(): void
    {
        if ($this->isItemEnabled('page', 'validation_notification')) {
            Pages\Page::$reportValidationErrorUsing = function (ValidationException $exception) {
                Notifications\Notification::make()
                    ->title($exception->getMessage())
                    ->danger()
                    ->send();
            };
        }

        if ($this->isItemEnabled('page', 'sticky_form_actions')) {
            Pages\Page::$formActionsAreSticky = false;
        }
    }

    protected function applyTableDefaults(): void
    {
        if ($this->isItemEnabled('table', 'table')) {
            Tables\Table::configureUsing(fn (Tables\Table $table) => $table
                ->filtersFormWidth('md')
                ->paginationPageOptions([5, 10, 25, 50])
            );
        }

        if ($this->isItemEnabled('table', 'image_column')) {
            Tables\Columns\ImageColumn::configureUsing(fn (Tables\Columns\ImageColumn $column) => $column
                ->extraImgAttributes(['loading' => 'lazy'])
            );
        }

        if ($this->isItemEnabled('table', 'select_filter')) {
            Tables\Filters\SelectFilter::configureUsing(fn (Tables\Filters\SelectFilter $filter) => $filter
                ->native(false)
            );
        }
    }

    protected function applyPanelDefaults(): void
    {
        if ($this->isItemEnabled('panel', 'top_navigation')) {
            Panel::configureUsing(fn (Panel $panel) => $panel
                ->topNavigation()
                ->maxContentWidth(Width::Full)
            );
        }

        if ($this->isItemEnabled('panel', 'full_width')) {
            Panel::configureUsing(fn (Panel $panel) => $panel
                ->maxContentWidth(Width::Full)
            );
        }
    }

    protected function applyFormatDefaults(): void
    {
        $formats = config('filament-defaults.formats', []);

        Schemas\Schema::configureUsing(function (Schemas\Schema $schema) use ($formats) {
            return $schema
                ->defaultCurrency($formats['currency'] ?? 'zar')
                ->defaultDateDisplayFormat($formats['date_display_format'] ?? 'j M Y')
                ->defaultIsoDateDisplayFormat($formats['iso_date_display_format'] ?? 'L')
                ->defaultDateTimeDisplayFormat($formats['datetime_display_format'] ?? 'j M Y, H:i')
                ->defaultIsoDateTimeDisplayFormat($formats['iso_datetime_display_format'] ?? 'LLL')
                ->defaultNumberLocale($formats['number_locale'] ?? null)
                ->defaultTimeDisplayFormat($formats['time_display_format'] ?? 'H:i:s')
                ->defaultIsoTimeDisplayFormat($formats['iso_time_display_format'] ?? 'LT');
        });

        Tables\Table::configureUsing(function (Tables\Table $table) use ($formats) {
            return $table
                ->defaultCurrency($formats['currency'] ?? 'zar')
                ->defaultDateDisplayFormat($formats['date_display_format'] ?? 'j M Y')
                ->defaultIsoDateDisplayFormat($formats['iso_date_display_format'] ?? 'L')
                ->defaultDateTimeDisplayFormat($formats['datetime_display_format'] ?? 'j M Y, H:i')
                ->defaultIsoDateTimeDisplayFormat($formats['iso_datetime_display_format'] ?? 'LLL')
                ->defaultNumberLocale($formats['number_locale'] ?? null)
                ->defaultTimeDisplayFormat($formats['time_display_format'] ?? 'H:i:s')
                ->defaultIsoTimeDisplayFormat($formats['iso_time_display_format'] ?? 'LT');
        });
    }
}

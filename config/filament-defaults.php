<?php

return [

    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Default blocks
    |--------------------------------------------------------------------------
    |
    | Each boolean toggles a related group of `::configureUsing()` defaults.
    | Turn any of them off to opt out of that block entirely. Blocks that
    | bundle more than one class/behaviour also expose a matching sub-array
    | below so you can disable a single item without losing the rest of the
    | block — the block switch acts as a master gate over its sub-items.
    |
    */

    // Field / Entry / Column → translateLabel()
    'translate_labels' => true,

    // Action / ActionGroup / Create|Edit|Delete|View action defaults
    'action_defaults' => true,

    // Select → native(false) + searchable/preload/selectablePlaceholder
    'select_defaults' => true,

    // DateTimePicker → seconds(false) + maxDate()
    'datetime_defaults' => true,

    // FileUpload → moveFiles()
    'fileupload_defaults' => true,

    // Repeater / Builder → delete action requires confirmation
    'repeater_defaults' => true,

    // ToggleButtons / TextInput / Textarea
    'form_defaults' => true,

    // Page → validation-error notification + non-sticky form actions
    'page_defaults' => true,

    // Table / ImageColumn / SelectFilter pagination & UX tweaks
    'table_defaults' => true,

    // Schema & Table display-format defaults (driven by the values below)
    'format_defaults' => true,

    /*
    |--------------------------------------------------------------------------
    | Action defaults (sub-items of `action_defaults`)
    |--------------------------------------------------------------------------
    */

    'actions' => [
        'action_group' => true,   // ActionGroup → ellipsis icon
        'action' => true,         // Action → modalWidth(Medium) + closeModalByClickingAway(false)
        'create_action' => true,  // CreateAction → icon + hiddenLabel() + createAnother(false)
        'edit_action' => true,    // EditAction → icon + hiddenLabel() + button()
        'delete_action' => true,  // DeleteAction → icon + hiddenLabel() + button()
        'view_action' => true,    // ViewAction → icon + hiddenLabel() + button()
        'restore_action' => true,    // RestoreAction → icon + hiddenLabel() + button()
        'force_delete_action' => true,    // ViewAction → icon + hiddenLabel() + button()
        'delete_bulk_action' => true,    // DeleteBulkAction → icon + hiddenLabel() + button()
        'force_delete_bulk_action' => true,    // ForceDeleteBulkAction → icon + hiddenLabel() + button()
        'restore_bulk_action' => true,    // RestoreBulkAction → icon + hiddenLabel() + button()
        'bulk_action_group' => true, // BulkActionGroup → ...
    ],

    /*
    |--------------------------------------------------------------------------
    | Select defaults (sub-items of `select_defaults`)
    |--------------------------------------------------------------------------
    */

    'select' => [
        'native' => true,                  // native(false)
        'selectable_placeholder' => true,  // selectablePlaceholder() when not required
        'searchable' => true,              // searchable() for relationships
        'preload' => true,                 // preload() when searchable
    ],

    /*
    |--------------------------------------------------------------------------
    | DateTimePicker defaults (sub-items of `datetime_defaults`)
    |--------------------------------------------------------------------------
    */

    'datetime' => [
        'seconds' => true,   // seconds(false)
        'max_date' => true,  // maxDate('9999-12-31T23:59')
    ],

    /*
    |--------------------------------------------------------------------------
    | Repeater defaults (sub-items of `repeater_defaults`)
    |--------------------------------------------------------------------------
    */

    'repeater' => [
        'repeater' => true,  // Repeater → delete action requires confirmation
        'builder' => true,   // Builder → delete action requires confirmation
    ],

    /*
    |--------------------------------------------------------------------------
    | Form defaults (sub-items of `form_defaults`)
    |--------------------------------------------------------------------------
    */

    'form' => [
        'toggle_buttons' => true,  // ToggleButtons → inline() + grouped()
        'text_input' => true,      // TextInput → minValue(0)
        'textarea' => true,        // Textarea → rows(4)
    ],

    /*
    |--------------------------------------------------------------------------
    | Page defaults (sub-items of `page_defaults`)
    |--------------------------------------------------------------------------
    */

    'page' => [
        'validation_notification' => true,  // validation errors → danger Notification
        'sticky_form_actions' => true,      // formActionsAreSticky(false)
    ],

    /*
    |--------------------------------------------------------------------------
    | Table defaults (sub-items of `table_defaults`)
    |--------------------------------------------------------------------------
    */

    'table' => [
        'table' => true,          // Table → filtersFormWidth('md') + paginationPageOptions
        'image_column' => true,   // ImageColumn → lazy loading
        'select_filter' => true,  // SelectFilter → native(false)
    ],

    /*
    |--------------------------------------------------------------------------
    | Panel defaults (top navigation bar, content width)
    |--------------------------------------------------------------------------
    |
    | top_navigation     Panel → top navigation instead of the sidebar.
    | max_content_width  Maximum width of the panel's main content container.
    |                    Any Filament\Support\Enums\Width value: '3xs' to '7xl',
    |                    'full', 'screen', 'screen-sm' to 'screen-2xl', 'prose',
    |                    'container', 'min', 'max', 'fit' or 'none'. Leave it null
    |                    to keep Filament's own default ('7xl'). Pages can still
    |                    set their own $maxContentWidth.
    |
    */

    'panel' => [
        'top_navigation' => true,
        'max_content_width' => 'container',
    ],

    /*
    |--------------------------------------------------------------------------
    | Display formats
    |--------------------------------------------------------------------------
    |
    | Applied to both Schema and Table via their `default*` helpers when the
    | `format_defaults` block is enabled.
    |
    */

    'formats' => [
        'currency' => 'zar',
        'date_display_format' => 'j M Y',
        'iso_date_display_format' => 'L',
        'datetime_display_format' => 'j M Y, H:i', // j M, Y H:i:s
        'iso_datetime_display_format' => 'LLL',
        'number_locale' => null,
        'time_display_format' => 'H:i:s',
        'iso_time_display_format' => 'LT',
    ],

];

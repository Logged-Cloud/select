<?php

/*
|--------------------------------------------------------------------------
| Logged Cloud · select component defaults
|--------------------------------------------------------------------------
|
| These are applied when an individual <x-select::*> does not pass an
| explicit attribute. The components are fully configurable per-instance;
| this file is for app-wide defaults.
|
*/

return [

    /*
    | Visual defaults · the fallback chain on each CSS variable is:
    |   --lc-select-<key>  (explicit override the host can set)
    |   then a common host-app var (--surface, --accent, …)
    |   then a hard-coded RGB picked from the fish.logged.cloud palette so
    |   an app that sets *nothing* still looks like a logged.cloud family
    |   app rather than a generic Tailwind grey.
    */
    'theme' => [
        'bg'         => 'var(--lc-select-bg, var(--surface-2, var(--surface, #25272A)))',
        'menu_bg'    => 'var(--lc-select-menu-bg, var(--surface-2, var(--surface, #25272A)))',
        'border'     => 'var(--lc-select-border, var(--line, #3A3D40))',
        'ink'        => 'var(--lc-select-ink, var(--ink, #F0EDE5))',
        'ink_dim'    => 'var(--lc-select-ink-dim, var(--ink-dim, #A3A099))',
        'accent'     => 'var(--lc-select-accent, var(--accent, #C7593A))',
        'icon_bg'    => 'var(--lc-select-icon-bg, var(--surface, #1E1F22))',
        'hover_bg'   => 'var(--lc-select-hover-bg, var(--line, #3A3D40))',
        'radius'     => 'var(--lc-select-radius, .5rem)',
    ],

    /*
    | Default copy. Override per instance with the matching attribute on
    | the component (placeholder="..." etc).
    */
    'copy' => [
        'placeholder'      => 'Select an option',
        'search_label'     => 'Search...',
        'empty_label'      => 'not set',
        'no_results_label' => 'No options match that.',
        'no_js_warning'    => 'JavaScript is needed for the rich picker. Using the basic select instead.',
        'no_js_indicator'  => 'JS off',
        'tags_placeholder' => 'Add a tag...',
    ],

    /*
    | Default behaviour. Override per instance.
    */
    'behavior' => [
        'allow_empty'   => true,
        'searchable'    => true,
        'icon_size'     => '1.75rem',
    ],

];

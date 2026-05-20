<?php

/*
|--------------------------------------------------------------------------
| Logged Cloud · select component defaults
|--------------------------------------------------------------------------
|
| These are applied when an individual <x-select::box> does not pass an
| explicit attribute. The component is fully configurable per-instance;
| this file is for app-wide defaults.
|
*/

return [

    /*
    | Visual defaults. Most apps will leave these as the CSS vars below
    | and override the underlying values in their own stylesheet; that
    | way the dropdown picks up the host app's theme without per-app
    | config changes.
    */
    'theme' => [
        // CSS custom properties consumed by the rendered markup. Override
        // any of these in your app's CSS to retheme. Defaults reference
        // a handful of common host-app vars so the dropdown blends in.
        'bg'         => 'var(--lc-select-bg, var(--surface-2, #1f2937))',
        'menu_bg'    => 'var(--lc-select-menu-bg, var(--surface-2, #1f2937))',
        'border'     => 'var(--lc-select-border, var(--line, #374151))',
        'ink'        => 'var(--lc-select-ink, var(--ink, #f9fafb))',
        'ink_dim'    => 'var(--lc-select-ink-dim, var(--ink-dim, #9ca3af))',
        'accent'     => 'var(--lc-select-accent, var(--accent, #0e9f6e))',
        'icon_bg'    => 'var(--lc-select-icon-bg, var(--surface, #111827))',
        'hover_bg'   => 'var(--lc-select-hover-bg, var(--line, #374151))',
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

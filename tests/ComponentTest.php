<?php

/*
| Static structural tests · the package doesn't boot Laravel, so we
| assert against the source files directly: ARIA attributes, keyboard
| handlers, focus management hooks, CSS hooks. Behavioural coverage
| (open / search / pick / keyboard sequences) lives in the consumer
| app's Dusk suite where a real browser drives the markup.
*/

$searchable = __DIR__.'/../resources/views/components/searchable-alpine.blade.php';
$multi      = __DIR__.'/../resources/views/components/multi-alpine.blade.php';
$radio      = __DIR__.'/../resources/views/components/radio-grid-alpine.blade.php';
$styles     = __DIR__.'/../resources/views/styles.blade.php';
$provider   = __DIR__.'/../src/SelectServiceProvider.php';

// ─── searchable-alpine ─────────────────────────────────────────────

test('searchable-alpine component file exists', function () use ($searchable) {
    expect(file_exists($searchable))->toBeTrue();
});

test('searchable-alpine renders the four required item fields', function () use ($searchable) {
    $template = file_get_contents($searchable);
    foreach (['opt.key', 'opt.title', 'opt.subtitle', 'opt.svg'] as $needle) {
        expect($template)->toContain($needle);
    }
});

test('searchable-alpine carries the full combobox + listbox ARIA roles', function () use ($searchable) {
    $template = file_get_contents($searchable);
    foreach ([
        'role="combobox"', 'role="listbox"', 'role="option"',
        'aria-haspopup="listbox"', 'aria-controls', 'aria-expanded',
        'aria-selected', 'aria-activedescendant', 'aria-live',
        'aria-autocomplete',
    ] as $needle) {
        expect($template)->toContain($needle);
    }
});

test('searchable-alpine keyboard handlers cover the combobox shortcuts', function () use ($searchable) {
    $template = file_get_contents($searchable);
    foreach ([
        'arrow-down.prevent', 'arrow-up.prevent',
        'home.prevent', 'end.prevent',
        'page-down.prevent', 'page-up.prevent',
        'enter.prevent', 'space.prevent',
        'keydown.escape.window', 'keydown.tab',
    ] as $needle) {
        expect($template)->toContain($needle);
    }
});

test('searchable-alpine returns focus to the trigger after select / clear / close', function () use ($searchable) {
    $template = file_get_contents($searchable);
    expect($template)
        ->toContain('focusTrigger')
        ->and($template)->toContain('x-ref="trigger"');
});

test('searchable-alpine surfaces label / labelledBy / required / disabled props', function () use ($searchable) {
    $template = file_get_contents($searchable);
    foreach (['$label', '$labelledBy', '$required', '$disabled'] as $prop) {
        expect($template)->toContain($prop);
    }
    foreach (['aria-required="true"', 'aria-disabled="true"'] as $aria) {
        expect($template)->toContain($aria);
    }
});

// ─── multi-alpine ──────────────────────────────────────────────────

test('multi-alpine component file exists', function () use ($multi) {
    expect(file_exists($multi))->toBeTrue();
});

test('multi-alpine announces aria-multiselectable on both trigger + listbox', function () use ($multi) {
    $template = file_get_contents($multi);
    expect(substr_count($template, 'aria-multiselectable="true"'))->toBeGreaterThanOrEqual(2);
});

test('multi-alpine posts hidden inputs using the name[] array convention', function () use ($multi) {
    $template = file_get_contents($multi);
    expect($template)
        ->toContain("'[]'")
        ->and($template)->toContain('input type="hidden"');
});

test('multi-alpine toggleAt does not close the menu', function () use ($multi) {
    $template = file_get_contents($multi);
    preg_match('/toggleAt\(i\) \{(.+?)\n\s{24}\},/s', $template, $m);
    expect($m[1] ?? '')->not->toBeEmpty()
        ->and($m[1])->not->toContain('this.open = false');
});

test('multi-alpine renders chips with a remove button per chip', function () use ($multi) {
    $template = file_get_contents($multi);
    expect($template)
        ->toContain('lc-select__chip-remove')
        ->and($template)->toContain('removeKey(opt.key)');
});

test('multi-alpine respects an optional max-selections limit', function () use ($multi) {
    $template = file_get_contents($multi);
    expect($template)->toContain('limit_reached');
});

test('multi-alpine carries the full ARIA + keyboard set the searchable one has', function () use ($multi) {
    $template = file_get_contents($multi);
    foreach ([
        'role="combobox"', 'role="listbox"', 'role="option"',
        'aria-haspopup="listbox"', 'aria-controls', 'aria-expanded',
        'aria-selected', 'aria-activedescendant', 'aria-live',
        'arrow-down.prevent', 'arrow-up.prevent',
        'home.prevent', 'end.prevent',
        'keydown.escape.window',
    ] as $needle) {
        expect($template)->toContain($needle);
    }
});

test('multi-alpine returns focus to the trigger after closing or removing a chip', function () use ($multi) {
    $template = file_get_contents($multi);
    expect($template)
        ->toContain('focusTrigger')
        ->and($template)->toContain('x-ref="trigger"');
});

// ─── radio-grid-alpine ─────────────────────────────────────────────

test('radio-grid-alpine component file exists', function () use ($radio) {
    expect(file_exists($radio))->toBeTrue();
});

test('radio-grid-alpine is a WAI radiogroup with role=radio children', function () use ($radio) {
    $template = file_get_contents($radio);
    expect($template)
        ->toContain('role="radiogroup"')
        ->and($template)->toContain('role="radio"')
        ->and($template)->toContain('aria-checked');
});

test('radio-grid-alpine implements the WAI radio-group roving-tabindex pattern', function () use ($radio) {
    $template = file_get_contents($radio);
    expect($template)
        ->toContain(':tabindex="(value === opt.key || (!value && i === 0)) ? 0 : -1"');
});

test('radio-grid-alpine arrow keys + home / end shortcuts wired up', function () use ($radio) {
    $template = file_get_contents($radio);
    foreach ([
        'arrow-right.prevent', 'arrow-down.prevent',
        'arrow-left.prevent', 'arrow-up.prevent',
        'home.prevent', 'end.prevent',
        'space.prevent', 'enter.prevent',
    ] as $needle) {
        expect($template)->toContain($needle);
    }
});

test('radio-grid-alpine posts a single value via a hidden input', function () use ($radio) {
    $template = file_get_contents($radio);
    expect($template)
        ->toContain('input type="hidden"')
        ->and($template)->toContain(':value="value"');
});

// ─── radio-list-alpine ─────────────────────────────────────────────

test('radio-list-alpine is a vertical WAI radiogroup', function () {
    $tpl = file_get_contents(__DIR__.'/../resources/views/components/radio-list-alpine.blade.php');
    expect($tpl)
        ->toContain('role="radiogroup"')
        ->and($tpl)->toContain('role="radio"')
        ->and($tpl)->toContain('aria-checked')
        ->and($tpl)->toContain('arrow-down.prevent')
        ->and($tpl)->toContain('arrow-up.prevent');
});

// ─── multi-grid-alpine ─────────────────────────────────────────────

test('multi-grid-alpine uses aria-pressed toggle-button semantics', function () {
    $tpl = file_get_contents(__DIR__.'/../resources/views/components/multi-grid-alpine.blade.php');
    expect($tpl)
        ->toContain('role="group"')
        ->and($tpl)->toContain('aria-pressed')
        ->and($tpl)->not->toContain('role="radio"');
});

test('multi-grid-alpine posts hidden inputs with name[] convention', function () {
    $tpl = file_get_contents(__DIR__.'/../resources/views/components/multi-grid-alpine.blade.php');
    expect($tpl)
        ->toContain("'[]'")
        ->and($tpl)->toContain('input type="hidden"');
});

test('multi-grid-alpine respects an optional max cap', function () {
    $tpl = file_get_contents(__DIR__.'/../resources/views/components/multi-grid-alpine.blade.php');
    expect($tpl)->toContain('limit_reached');
});

// ─── multi-list-alpine ─────────────────────────────────────────────

test('multi-list-alpine is a vertical group of toggle buttons', function () {
    $tpl = file_get_contents(__DIR__.'/../resources/views/components/multi-list-alpine.blade.php');
    expect($tpl)
        ->toContain('role="group"')
        ->and($tpl)->toContain('aria-pressed')
        ->and($tpl)->toContain('lc-multi-list__item');
});

// ─── inline-buttons-alpine ─────────────────────────────────────────

test('inline-buttons-alpine is a horizontal radiogroup with left/right keys', function () {
    $tpl = file_get_contents(__DIR__.'/../resources/views/components/inline-buttons-alpine.blade.php');
    expect($tpl)
        ->toContain('role="radiogroup"')
        ->and($tpl)->toContain('role="radio"')
        ->and($tpl)->toContain('arrow-right.prevent')
        ->and($tpl)->toContain('arrow-left.prevent')
        ->and($tpl)->not->toContain('arrow-down.prevent');
});

// ─── card-single-alpine ────────────────────────────────────────────

test('card-single-alpine is a WAI radiogroup with 4-direction navigation', function () {
    $tpl = file_get_contents(__DIR__.'/../resources/views/components/card-single-alpine.blade.php');
    expect($tpl)
        ->toContain('role="radiogroup"')
        ->and($tpl)->toContain('role="radio"')
        ->and($tpl)->toContain('arrow-right.prevent')
        ->and($tpl)->toContain('arrow-down.prevent')
        ->and($tpl)->toContain('arrow-left.prevent')
        ->and($tpl)->toContain('arrow-up.prevent');
});

// ─── card-multi-alpine ─────────────────────────────────────────────

test('card-multi-alpine uses toggle-button semantics + name[] posting', function () {
    $tpl = file_get_contents(__DIR__.'/../resources/views/components/card-multi-alpine.blade.php');
    expect($tpl)
        ->toContain('role="group"')
        ->and($tpl)->toContain('aria-pressed')
        ->and($tpl)->toContain("'[]'")
        ->and($tpl)->toContain('limit_reached');
});

// ─── CSS hooks for the v2.2 variants ───────────────────────────────

test('styles ship CSS hooks for every v2.2 variant', function () {
    $css = file_get_contents(__DIR__.'/../resources/views/styles.blade.php');
    foreach ([
        '.lc-radio-list', '.lc-radio-list__item', '.lc-radio-list__dot',
        '.lc-multi-grid', '.lc-multi-grid__item', '.lc-multi-grid__check',
        '.lc-multi-list', '.lc-multi-list__item',
        '.lc-inline-buttons', '.lc-inline-buttons__item',
        '.lc-cards', '.lc-cards__item', '.lc-cards__check',
    ] as $hook) {
        expect($css)->toContain($hook);
    }
});

test('forced-colors block covers every variant', function () {
    $css = file_get_contents(__DIR__.'/../resources/views/styles.blade.php');
    foreach ([
        '.lc-radio-list__item', '.lc-radio-grid__item',
        '.lc-multi-grid__item', '.lc-multi-list__item',
        '.lc-inline-buttons__item', '.lc-cards__item',
    ] as $hook) {
        expect($css)->toContain($hook);
    }
});

// ─── styles ────────────────────────────────────────────────────────

test('styles file ships CSS hooks for every variant', function () use ($styles) {
    $css = file_get_contents($styles);
    foreach ([
        '.lc-select__trigger',
        '.lc-select__menu',
        '.lc-select__item',
        '.lc-select__trigger--multi',
        '.lc-select__chip',
        '.lc-select__chip-remove',
        '.lc-select__check',
        '.lc-radio-grid',
        '.lc-radio-grid__item',
    ] as $hook) {
        expect($css)->toContain($hook);
    }
});

test('styles respect prefers-reduced-motion and forced-colors', function () use ($styles) {
    $css = file_get_contents($styles);
    expect($css)
        ->toContain('prefers-reduced-motion')
        ->and($css)->toContain('forced-colors');
});

test('styles use system colours under forced-colors mode', function () use ($styles) {
    $css = file_get_contents($styles);
    foreach (['CanvasText', 'Highlight', 'HighlightText'] as $token) {
        expect($css)->toContain($token);
    }
});

// ─── progressive enhancement fallback ──────────────────────────────

test('every variant includes the no-JS fallback partial', function () {
    $dir = __DIR__.'/../resources/views/components';
    foreach (glob($dir.'/*.blade.php') as $path) {
        $tpl = file_get_contents($path);
        expect($tpl)->toContain("@include('select::partials.fallback'");
    }
});

test('fallback partial renders a native select with name behind noscript styling', function () {
    $tpl = file_get_contents(__DIR__.'/../resources/views/partials/fallback.blade.php');
    expect($tpl)
        ->toContain('<noscript>')
        ->and($tpl)->toContain('<select')
        ->and($tpl)->toContain('data-lc-fallback')
        ->and($tpl)->toContain('class="lc-no-js"')
        ->and($tpl)->toContain('data-lc-no-js');
});

test('every variant clears the native fallback name when Alpine boots', function () {
    $dir = __DIR__.'/../resources/views/components';
    foreach (glob($dir.'/*.blade.php') as $path) {
        $tpl = file_get_contents($path);
        expect($tpl)->toContain('$refs.fallback');
    }
});

// ─── label-derived ids ─────────────────────────────────────────────

test('every variant derives the id from the label as camelCase', function () {
    $dir = __DIR__.'/../resources/views/components';
    foreach (glob($dir.'/*.blade.php') as $path) {
        $tpl = file_get_contents($path);
        expect($tpl)
            ->toContain('Str::camel')
            ->and($tpl)->toContain('Str::slug');
    }
});

// ─── default colour palette ────────────────────────────────────────

test('default --lc-accent falls back to the fish.logged.cloud terracotta', function () {
    $config = require __DIR__.'/../config/select.php';
    expect($config['theme']['accent'])->toContain('#C7593A');
});

test('config exposes no-JS copy', function () {
    $config = require __DIR__.'/../config/select.php';
    expect($config['copy'])
        ->toHaveKey('no_js_warning')
        ->toHaveKey('no_js_indicator');
});

// ─── card borders ──────────────────────────────────────────────────

test('every card-style variant has a 2px border', function () {
    $css = file_get_contents(__DIR__.'/../resources/views/styles.blade.php');
    expect(substr_count($css, 'border: 2px solid var(--lc-border)'))->toBeGreaterThanOrEqual(4);
});

// ─── service provider ──────────────────────────────────────────────

test('service provider loads namespaced views and publishes config + views', function () use ($provider) {
    $src = file_get_contents($provider);
    expect($src)
        ->toContain('loadViewsFrom')
        ->and($src)->toContain("'select'")
        ->and($src)->toContain('select-config')
        ->and($src)->toContain('select-views');
});

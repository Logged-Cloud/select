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

// ─── service provider ──────────────────────────────────────────────

test('service provider loads namespaced views and publishes config + views', function () use ($provider) {
    $src = file_get_contents($provider);
    expect($src)
        ->toContain('loadViewsFrom')
        ->and($src)->toContain("'select'")
        ->and($src)->toContain('select-config')
        ->and($src)->toContain('select-views');
});

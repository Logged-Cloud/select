<?php

test('the component template exists at the namespaced-component path', function () {
    expect(file_exists(__DIR__.'/../resources/views/components/box.blade.php'))->toBeTrue();
});

test('the component template references the four required item fields', function () {
    $template = file_get_contents(__DIR__.'/../resources/views/components/box.blade.php');

    foreach (['opt.key', 'opt.title', 'opt.subtitle', 'opt.svg'] as $needle) {
        expect($template)->toContain($needle);
    }
});

test('the service provider registers the namespaced view loader', function () {
    $src = file_get_contents(__DIR__.'/../src/SelectServiceProvider.php');

    expect($src)
        ->toContain('loadViewsFrom')
        ->and($src)->toContain("'select'")
        ->and($src)->toContain('select-config')
        ->and($src)->toContain('select-views');
});

test('the component template carries combobox + listbox ARIA roles', function () {
    $template = file_get_contents(__DIR__.'/../resources/views/components/box.blade.php');

    foreach (['role="combobox"', 'role="listbox"', 'role="option"', 'aria-haspopup="listbox"', 'aria-controls', 'aria-expanded', 'aria-selected', 'aria-activedescendant', 'aria-live'] as $needle) {
        expect($template)->toContain($needle);
    }
});

test('keyboard handlers cover the core combobox shortcuts', function () {
    $template = file_get_contents(__DIR__.'/../resources/views/components/box.blade.php');

    foreach (['arrow-down.prevent', 'arrow-up.prevent', 'home.prevent', 'end.prevent', 'enter.prevent', 'keydown.escape.window'] as $needle) {
        expect($template)->toContain($needle);
    }
});

test('focus returns to the trigger after select or clear', function () {
    $template = file_get_contents(__DIR__.'/../resources/views/components/box.blade.php');

    expect($template)
        ->toContain('focusTrigger')
        ->and($template)->toContain('x-ref="trigger"');
});

test('respects prefers-reduced-motion and forced-colors', function () {
    $styles = file_get_contents(__DIR__.'/../resources/views/styles.blade.php');

    expect($styles)
        ->toContain('prefers-reduced-motion')
        ->and($styles)->toContain('forced-colors');
});

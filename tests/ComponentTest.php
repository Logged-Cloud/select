<?php

test('the component template exists', function () {
    expect(file_exists(__DIR__.'/../resources/views/box.blade.php'))->toBeTrue();
});

test('the component template references the four required item fields', function () {
    $template = file_get_contents(__DIR__.'/../resources/views/box.blade.php');

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

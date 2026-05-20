<?php

test('config defaults are loaded', function () {
    $config = require __DIR__.'/../config/select.php';

    expect($config)->toBeArray()
        ->and($config)->toHaveKeys(['theme', 'copy', 'behavior'])
        ->and($config['behavior']['allow_empty'])->toBeTrue()
        ->and($config['behavior']['searchable'])->toBeTrue()
        ->and($config['copy']['placeholder'])->toBe('Select an option');
});

test('theme keys are all CSS custom properties referencing host-app fallbacks', function () {
    $theme = (require __DIR__.'/../config/select.php')['theme'];

    foreach ($theme as $key => $value) {
        expect($value)->toStartWith('var(')
            ->and($value)->toContain('--lc-select-'.str_replace('_', '-', $key));
    }
});

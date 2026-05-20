<?php

namespace LoggedCloud\Select;

use Illuminate\Support\ServiceProvider;

class SelectServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/select.php', 'select');
    }

    public function boot(): void
    {
        // Views are namespaced `select::*`; the component renders as
        // <x-select::box />.
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'select');

        $this->publishes([
            __DIR__.'/../config/select.php' => config_path('select.php'),
        ], 'select-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/select'),
        ], 'select-views');
    }
}

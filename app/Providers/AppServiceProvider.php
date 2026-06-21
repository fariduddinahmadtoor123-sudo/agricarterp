<?php

namespace App\Providers;

use App\Models\ContactMobileNumber;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            ContactMobileNumber::CONTACTABLE_SUPPLIER => Supplier::class,
        ]);

        $path = parse_url(config('app.url'), PHP_URL_PATH) ?: '';

        if ($path !== '' && $path !== '/') {
            $prefix = rtrim($path, '/');

            Livewire::setUpdateRoute(function ($handle) use ($prefix) {
                return Route::post("{$prefix}/livewire/update", $handle);
            });

            Livewire::setScriptRoute(function ($handle) use ($prefix) {
                return Route::get("{$prefix}/livewire/livewire.js", $handle);
            });
        }
    }
}

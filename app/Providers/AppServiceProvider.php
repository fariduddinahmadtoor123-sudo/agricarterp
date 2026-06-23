<?php

namespace App\Providers;

use App\Filament\Contacts\Support\ContactsListToolbar;
use App\Filament\ProductCatalog\Support\ProductCatalogListToolbar;
use App\Models\ContactMobileNumber;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
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
        ContactsListToolbar::register();
        ProductCatalogListToolbar::register();
        \App\Filament\PurchasingInventory\Support\PurchasingInventoryListToolbar::register();

        Relation::enforceMorphMap([
            ContactMobileNumber::CONTACTABLE_SUPPLIER => Supplier::class,
            ContactMobileNumber::CONTACTABLE_CUSTOMER => Customer::class,
        ]);

        if (! $this->app->runningInConsole() && $this->app->bound('request')) {
            $request = $this->app->make('request');
            $rootUrl = rtrim($request->getSchemeAndHttpHost() . $request->getBaseUrl(), '/');

            if ($rootUrl !== $request->getSchemeAndHttpHost()) {
                URL::forceRootUrl($rootUrl);
            }
        }

        $path = parse_url(config('app.url'), PHP_URL_PATH) ?: '';

        if ($path !== '' && $path !== '/') {
            $prefix = rtrim($path, '/');
        } elseif (! $this->app->runningInConsole() && $this->app->bound('request')) {
            $prefix = rtrim($this->app->make('request')->getBaseUrl(), '/');
        } else {
            $prefix = '';
        }

        if ($prefix !== '') {
            Livewire::setUpdateRoute(function ($handle) use ($prefix) {
                return Route::post("{$prefix}/livewire/update", $handle);
            });

            Livewire::setScriptRoute(function ($handle) use ($prefix) {
                return Route::get("{$prefix}/livewire/livewire.js", $handle);
            });
        }
    }
}

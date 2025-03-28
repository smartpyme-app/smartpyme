<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Observers\InventarioObserver;
use App\Observers\ProductoObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Inventario::observe(InventarioObserver::class);
        Producto::observe(ProductoObserver::class);

        Schema::defaultStringLength(191);
        \Carbon\Carbon::setLocale(config('app.locale'));
        setlocale(LC_ALL,'es_ES.UTF8');
        setlocale (LC_TIME,'es_ES.UTF8');

    }
}

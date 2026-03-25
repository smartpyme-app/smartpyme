<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Producto;
use App\Models\Ventas\Venta;
use App\Observers\InventarioObserver;
use App\Observers\ProductoObserver;
use App\Observers\ShopifyInventarioObserver;
use App\Observers\ShopifyProductoObserver;
use Illuminate\Support\Facades\Auth;

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
        // Registrar el observer de Inventario
        Inventario::observe(InventarioObserver::class);

        // Registrar el observer de Producto solo cuando sea necesario
        if (config('services.woocommerce.enabled', false)) {
            Producto::observe(ProductoObserver::class);
        }


        Inventario::observe(ShopifyInventarioObserver::class);
        Producto::observe(ShopifyProductoObserver::class);

        // Registra este scope nuevamente para asegurarte de que se aplique después del observer
        Producto::addGlobalScope('empresa', function ($builder) {
            if (Auth::check()) {
                $builder->where('id_empresa', Auth::user()->id_empresa);
            }
        });

        Schema::defaultStringLength(191);
        \Carbon\Carbon::setLocale(config('app.locale'));
        setlocale(LC_ALL, 'es_ES.UTF8');
        setlocale(LC_TIME, 'es_ES.UTF8');
    }
}

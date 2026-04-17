<?php

namespace App\Providers;

use App\Services\AIService;
use App\Services\ContextService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\Paquete;
use App\Models\Inventario\Producto;
use App\Models\Ventas\Clientes\Cliente;
use App\Models\Ventas\Venta;
use App\Observers\FidelizacionCliente\ClienteNivelObserver;
use App\Observers\InventarioObserver;
use App\Observers\PaqueteWebhookObserver;
use App\Observers\FidelizacionCliente\VentaObserver;
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
        $this->app->singleton(ContextService::class, function ($app) {
            return new ContextService();
        });

        $this->app->singleton(AIService::class, function ($app) {
            return new AIService();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->ensureOpenSslCliOnPathWindows();

        // Registrar el observer de Inventario
        Cliente::observe(ClienteNivelObserver::class);
        Inventario::observe(InventarioObserver::class);

        // Observer para acumulación de puntos de fidelización
        Venta::observe(VentaObserver::class);

        Paquete::observe(PaqueteWebhookObserver::class);

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

    /**
     * dgt-cr-signer ejecuta `openssl` por CLI; en Windows Apache/Laragon/php-fpm suelen no heredar
     * el PATH del usuario. Anteponemos la carpeta bin si encontramos openssl.exe.
     */
    private function ensureOpenSslCliOnPathWindows(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        $path = getenv('PATH');
        if ($path === false) {
            $path = '';
        }

        $binDir = $this->resolveOpenSslBinDirectory();
        if ($binDir === null) {
            return;
        }

        $binDirLower = strtolower($binDir);
        foreach (explode(PATH_SEPARATOR, $path) as $segment) {
            if ($segment !== '' && strtolower($segment) === $binDirLower) {
                return;
            }
        }

        $merged = $binDir.PATH_SEPARATOR.$path;
        putenv('PATH='.$merged);
        $_ENV['PATH'] = $merged;
        $_SERVER['PATH'] = $merged;
    }

    private function resolveOpenSslBinDirectory(): ?string
    {
        $configured = config('services.openssl_bin');
        if (is_string($configured) && $configured !== '') {
            $configured = str_replace('/', DIRECTORY_SEPARATOR, $configured);
            if (is_file($configured) && str_ends_with(strtolower($configured), '.exe')) {
                return dirname($configured);
            }
            $asDir = rtrim($configured, DIRECTORY_SEPARATOR);
            if (is_file($asDir.DIRECTORY_SEPARATOR.'openssl.exe')) {
                return $asDir;
            }
        }

        $candidates = [
            'C:'.DIRECTORY_SEPARATOR.'Program Files'.DIRECTORY_SEPARATOR.'OpenSSL-Win64'.DIRECTORY_SEPARATOR.'bin',
            'C:'.DIRECTORY_SEPARATOR.'OpenSSL-Win64'.DIRECTORY_SEPARATOR.'bin',
            'C:'.DIRECTORY_SEPARATOR.'Program Files (x86)'.DIRECTORY_SEPARATOR.'OpenSSL-Win32'.DIRECTORY_SEPARATOR.'bin',
        ];
        $programFiles = getenv('ProgramFiles');
        if (is_string($programFiles) && $programFiles !== '') {
            $candidates[] = $programFiles.DIRECTORY_SEPARATOR.'OpenSSL-Win64'.DIRECTORY_SEPARATOR.'bin';
        }

        foreach ($candidates as $dir) {
            if (is_file($dir.DIRECTORY_SEPARATOR.'openssl.exe')) {
                return $dir;
            }
        }

        return null;
    }
}

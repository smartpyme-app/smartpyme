<?php

namespace App\Providers;

use App\Services\AIService;
use App\Services\ContextService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

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
        Schema::defaultStringLength(191);
        \Carbon\Carbon::setLocale(config('app.locale'));
        setlocale(LC_ALL,'es_ES.UTF8');
        setlocale (LC_TIME,'es_ES.UTF8');

    }
}

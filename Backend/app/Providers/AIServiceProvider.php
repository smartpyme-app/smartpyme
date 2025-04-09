<?php

namespace App\Providers;

use App\Services\AIService;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(AIService::class, function ($app) {
            $defaultModel = config('bedrock.default_model', 'haiku');
            return new AIService($defaultModel);
        });
    }

    public function boot()
    {
        //
    }
}
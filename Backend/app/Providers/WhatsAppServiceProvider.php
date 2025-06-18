<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\WhatsApp\WhatsAppService;
use App\Services\WhatsApp\MessageHandler;
use App\Services\WhatsApp\ResponseBuilder;

class WhatsAppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(ResponseBuilder::class, function ($app) {
            return new ResponseBuilder();
        });

        $this->app->singleton(MessageHandler::class, function ($app) {
            return new MessageHandler();
        });

        $this->app->singleton(WhatsAppService::class, function ($app) {
            return new WhatsAppService(
                $app->make(MessageHandler::class),
                $app->make(ResponseBuilder::class)
            );
        });
    }

    public function boot()
    {
    }
}
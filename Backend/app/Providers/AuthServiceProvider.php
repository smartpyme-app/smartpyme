<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Registrar el driver JWT para Laravel 9
        Auth::extend('jwt', function ($app, $name, array $config) {
            return new \Tymon\JWTAuth\JWTGuard(
                $app->make(\Tymon\JWTAuth\JWT::class),
                Auth::createUserProvider($config['provider'] ?? null),
                $app['request']
            );
        });
    }
}

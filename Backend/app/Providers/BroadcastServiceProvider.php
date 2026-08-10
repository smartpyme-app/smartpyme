<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Auth JWT (mismo guard que el API restaurante). Prefijo api → POST /api/broadcasting/auth
        Broadcast::routes([
            'middleware' => ['api', 'jwt.auth'],
            'prefix' => 'api',
        ]);

        require base_path('routes/channels.php');
    }
}

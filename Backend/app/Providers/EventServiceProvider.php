<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

use App\Listeners\UserEventSubscriber;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class
        ],  
        
        'App\Events\AuthorizationApproved' => [
            'App\Listeners\HandleAuthorizationApproved',
        ],
        'App\Events\AuthorizationRejected' => [
            'App\Listeners\HandleAuthorizationRejected',
        ],
        'App\Events\AuthorizationApproved' => [
            'App\Listeners\Authorization\AuthorizationApprovedListener',
        ],
    ];

    protected $subscribe = [
        UserEventSubscriber::class,
    ];


    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}

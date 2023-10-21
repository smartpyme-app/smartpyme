<?php

namespace App\Listeners;

use Carbon\Carbon;

class UserEventSubscriber
{
    /**
     * Handle user login events.
     */
    public function handleUserLogin($event) {
        $event->user->ultimo_login = Carbon::now();
        $event->user->save();
    }

    /**
     * Handle user logout events.
     */
    public function handleUserLogout($event) {

        $event->user->ultimo_logout = Carbon::now();
        $event->user->save();

    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param  Illuminate\Events\Dispatcher  $events
     */
    public function subscribe($events)
    {
        $events->listen(
            'Illuminate\Auth\Events\Login',
            [UserEventSubscriber::class, 'handleUserLogin']
        );

        $events->listen(
            'Illuminate\Auth\Events\Logout',
            [UserEventSubscriber::class, 'handleUserLogout']
        );
    }

}

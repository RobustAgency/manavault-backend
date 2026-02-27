<?php

namespace App\Listeners\Admin;

use App\Models\User;
use App\Enums\UserRole;
use App\Events\UserCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Admin\NewAccountNotification;

class SendNewAccountNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(UserCreated $event): void
    {
        // Can be changed according to the requirements
        $admins = User::where('role', UserRole::ADMIN->value)->get();

        foreach ($admins as $admin) {
            $admin->notify(new NewAccountNotification($event->user));
        }
    }
}

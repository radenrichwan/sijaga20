<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\InternalNotification;
use Illuminate\Support\Collection;

class InternalNotificationService
{
    /**
     * @param  iterable<User>  $recipients
     * @param  array<string, mixed>  $details
     */
    public function send(
        iterable $recipients,
        string $event,
        string $title,
        string $message,
        ?string $url = null,
        array $details = [],
    ): void {
        $users = $recipients instanceof Collection ? $recipients : collect($recipients);

        foreach ($users->filter()->unique('id') as $user) {
            $user->notify(new InternalNotification($event, $title, $message, $url, $details));
        }
    }
}

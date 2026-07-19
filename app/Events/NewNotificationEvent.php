<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewNotificationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;

    public function __construct($notification)
    {
        $this->notification = $notification;
    }

    // Menentukan channel mana yang akan menerima event ini
    public function broadcastOn(): array
    {
        // Menggunakan PrivateChannel agar hanya user yang bersangkutan yang menerima
        return [
            new PrivateChannel('App.Models.User.' . $this->notification->user_id),
        ];
    }

    // Opsional: Menentukan nama event yang dikirimkan (alias)
    public function broadcastAs(): string
    {
        return 'NewNotificationEvent';
    }
}

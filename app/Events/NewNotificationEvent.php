<?php

namespace App\Events;

use App\Models\OwnerNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewNotificationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notification;

    public function __construct(OwnerNotification $notification)
    {
        $this->notification = $notification;
    }

    // Menggunakan Channel publik agar mudah didengar di lokal tanpa ribet autentikasi privat saat demo TA
    public function broadcastOn(): array
    {
        return [
            new Channel('owner-updates'),
        ];
    }

    // Nama event yang akan ditangkap oleh JavaScript Echo Anda
    public function broadcastAs(): string
    {
        return 'new-notification';
    }
}

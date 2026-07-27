<?php

namespace App\Events;

use App\Models\Chat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chat;

    public function __construct(Chat $chat)
    {
        $this->chat = $chat;
    }

    // Menggunakan private channel yang terikat pada order_id tertentu
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->chat->order_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}

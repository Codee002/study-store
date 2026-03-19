<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationPushed implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public Notification $notification;

    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.' . $this->notification->user_id);
    }

    public function broadcastAs(): string
    {
        return 'NotificationPushed';
    }

    public function broadcastWith(): array
    {
        return [
            'id'         => (int) $this->notification->id,
            'type'       => (string) $this->notification->type,
            'content'    => (string) $this->notification->content,
            'url_id'     => (int) $this->notification->url_id,
            'status'     => (string) $this->notification->status,
            'read_at'    => $this->notification->read_at,
            'created_at' => $this->notification->created_at,
        ];
    }
}

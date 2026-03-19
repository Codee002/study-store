<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public Message $message;

    public function __construct(Message $message)
    {
        // Load what the frontend needs for realtime updates
        $this->message = $message->loadMissing([
            'medias',
            'conversation.users',
        ]);
    }

    public function broadcastOn(): array
    {
        // Push to every participant in the conversation
        return $this->message->conversation->users
            ->map(fn ($user) => new PrivateChannel('user.' . $user->id))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->message->conversation_id,
            'id'              => $this->message->id,
            'user_id'         => $this->message->user_id,
            'content'         => $this->message->content,
            'type'            => $this->message->type,
            'created_at'      => $this->message->created_at,
            'read_by_user_ids' => $this->message->reads->pluck('user_id')->values(),
            'medias'          => $this->message->medias->map(function ($media) {
                return [
                    'id'   => $media->id,
                    'name' => $media->name,
                    'url'  => $media->url,
                    'type' => $media->type,
                ];
            })->values(),
        ];
    }
}

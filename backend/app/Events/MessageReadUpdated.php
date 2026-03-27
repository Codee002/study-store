<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReadUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public Conversation $conversation;
    public int $readerId;
    public array $messages;

    public function __construct(Conversation $conversation, int $readerId, array $messages)
    {
        $this->conversation = $conversation->loadMissing('users');
        $this->readerId = $readerId;
        $this->messages = $messages;
    }

    public function broadcastOn(): array
    {
        return $this->conversation->users
            ->map(fn ($user) => new PrivateChannel('user.' . $user->id))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'MessageReadUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'reader_id' => $this->readerId,
            'messages' => $this->messages,
        ];
    }
}

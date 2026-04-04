<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\ChatboxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class GenerateChatboxReplyJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $messageId)
    {
    }

    public function handle(ChatboxService $chatbox): void
    {
        $message = Message::query()->find($this->messageId);
        if (! $message) {
            return;
        }

        $chatbox->replyToMessage($message);
    }
}

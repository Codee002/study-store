<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageMedia;
use App\Models\MessageRead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    public function ensureCustomerConversation(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'user') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ khách hàng mới sử dụng được kênh này.',
            ], 403);
        }

        $admin = User::query()
            ->where('role', 'admin')
            ->where('status', 'actived')
            ->orderBy('id')
            ->first();

        if (! $admin) {
            return response()->json([
                'success' => false,
                'message' => 'Chưa cấu hình tài khoản admin để nhận tin nhắn.',
            ], 404);
        }

        $conversation = Conversation::query()
            ->where('type', 'private')
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->whereHas('users', fn ($q) => $q->where('users.id', $admin->id))
            ->first();

        if (! $conversation) {
            DB::transaction(function () use (&$conversation, $user, $admin) {
                $conversation = Conversation::query()->create([
                    'type' => 'private',
                    'name' => null,
                ]);

                $conversation->users()->attach([$user->id, $admin->id]);
            });
        }

        $admin->load('profile');

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'name' => $conversation->name,
                'thumb' => $conversation->thumb,
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->profile->name ?? $admin->username,
                    'avatar' => $admin->profile->avatar,
                ],
            ],
        ]);
    }

    public function ensureAdminConversation(Request $request, User $customer)
    {
        $admin = $request->user();

        if ($admin->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ admin mới được mở hộp thoại với khách hàng.',
            ], 403);
        }

        if ($customer->role !== 'user') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ có thể trò chuyện với tài khoản khách hàng.',
            ], 422);
        }

        $conversation = Conversation::query()
            ->where('type', 'private')
            ->whereHas('users', fn ($q) => $q->where('users.id', $admin->id))
            ->whereHas('users', fn ($q) => $q->where('users.id', $customer->id))
            ->first();

        if (! $conversation) {
            DB::transaction(function () use (&$conversation, $admin, $customer) {
                $conversation = Conversation::query()->create([
                    'type' => 'private',
                    'name' => null,
                ]);

                $conversation->users()->attach([$admin->id, $customer->id]);
            });
        }

        $customer->load('profile');

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'name' => $conversation->name,
                'thumb' => $conversation->thumb,
                'user' => [
                    'id' => $customer->id,
                    'name' => $customer->profile->name ?? $customer->username,
                    'avatar' => $customer->profile->avatar,
                ],
            ],
        ]);
    }

    public function adminContacts(Request $request)
    {
        $admin = $request->user();

        if ($admin->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ admin mới được xem danh sách liên hệ.',
            ], 403);
        }

        $keyword = trim((string) $request->query('q', ''));

        $users = User::query()
            ->with('profile')
            ->where('role', 'user')
            ->when($keyword !== '', function ($q) use ($keyword) {
                $q->where(function ($inner) use ($keyword) {
                    $inner->where('username', 'like', "%{$keyword}%")
                        ->orWhereHas('profile', function ($p) use ($keyword) {
                            $p->where('name', 'like', "%{$keyword}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $contacts = [];

        foreach ($users as $user) {
            $conversation = Conversation::query()
                ->where('type', 'private')
                ->whereHas('users', fn ($q) => $q->where('users.id', $admin->id))
                ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
                ->with(['messages' => function ($q) {
                    $q->latest()->limit(1);
                }])
                ->first();

            $lastMessage = optional($conversation?->messages?->first());
            $lastContent = null;
            if ($lastMessage) {
                if ($lastMessage->type === 'media') {
                    $lastContent = 'Đã gửi một tệp';
                } else {
                    $lastContent = $lastMessage->content;
                }
            }

            $unread = 0;
            if ($conversation) {
                $unread = Message::query()
                    ->where('conversation_id', $conversation->id)
                    ->where('user_id', $user->id) // tin nhắn từ khách
                    ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $admin->id))
                    ->count();
            }

            $contacts[] = [
                'id' => $user->id,
                'name' => $user->profile->name ?? $user->username,
                'avatar' => $user->profile->avatar,
                'conversation_id' => $conversation?->id,
                'last_message' => $lastContent,
                'updated_at' => $lastMessage?->created_at,
                'unread' => $unread,
            ];
        }

        return response()->json([
            'success' => true,
            'contacts' => $contacts,
        ]);
    }

    public function fetchMessages(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        if (! $conversation->users()->where('users.id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền truy cập cuộc trò chuyện này.',
            ], 403);
        }

        $messages = $conversation->messages()
            ->with(['medias', 'reads'])
            ->orderBy('created_at')
            ->get()
            ->map(function (Message $message) use ($user) {
                $readIds = $message->reads->pluck('user_id')->all();
                return [
                    'id' => $message->id,
                    'user_id' => $message->user_id,
                    'content' => $message->content,
                    'type' => $message->type,
                    'created_at' => $message->created_at,
                    'read_by_user_ids' => $readIds,
                    'is_read' => in_array($user->id, $readIds, true),
                    'medias' => $message->medias->map(function (MessageMedia $media) {
                        return [
                            'id' => $media->id,
                            'name' => $media->name,
                            'url' => $media->url,
                            'type' => $media->type,
                        ];
                    })->values(),
                ];
            });

        // mark as read for current user (only incoming)
        $toInsert = [];
        foreach ($conversation->messages as $message) {
            $already = $message->reads->firstWhere('user_id', $user->id);
            if ($message->user_id !== $user->id && ! $already) {
                $toInsert[] = [
                    'message_id' => $message->id,
                    'user_id' => $user->id,
                    'read_at' => now(),
                ];
            }
        }
        if ($toInsert) {
            MessageRead::insertOrIgnore($toInsert);
        }

        return response()->json([
            'success' => true,
            'messages' => $messages,
        ]);
    }

    public function send(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        if (! $conversation->users()->where('users.id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền gửi tin nhắn ở cuộc trò chuyện này.',
            ], 403);
        }

        $validated = $request->validate([
            'content' => ['nullable', 'string'],
            'files.*' => ['sometimes', 'file', 'max:10240'],
        ]);

        $files = $request->file('files', []);

        if (empty($validated['content']) && empty($files)) {
            return response()->json([
                'success' => false,
                'message' => 'Nội dung tin nhắn trống.',
            ], 422);
        }

        $type = count($files) ? 'media' : 'text';
        $message = null;

        DB::transaction(function () use (&$message, $conversation, $user, $validated, $files, $type) {
            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'content' => $validated['content'] ?? null,
                'type' => $type,
            ]);

            MessageRead::create([
                'message_id' => $message->id,
                'user_id' => $user->id,
                'read_at' => now(),
            ]);

            foreach ($files as $file) {
                $upload = cloudinary()->uploadApi()->upload($file->getRealPath(), [
                    'folder' => 'messages',
                    'resource_type' => 'auto',
                ]);

                $mediaType = $this->detectMediaType($file->getMimeType());

                MessageMedia::query()->create([
                    'message_id' => $message->id,
                    'name' => $file->getClientOriginalName(),
                    'url' => $upload['secure_url'] ?? $upload['url'] ?? null,
                    'public_id' => $upload['public_id'] ?? null,
                    'type' => $mediaType,
                ]);
            }
        });

        $message->load(['medias', 'reads']);

        event(new MessageSent($message));

        return response()->json([
            'success' => true,
            'message' => 'Gửi tin nhắn thành công.',
            'data' => [
                'id' => $message->id,
                'user_id' => $message->user_id,
                'content' => $message->content,
                'type' => $message->type,
                'created_at' => $message->created_at,
                'read_by_user_ids' => $message->reads->pluck('user_id')->values(),
                'medias' => $message->medias->map(function (MessageMedia $media) {
                    return [
                        'id' => $media->id,
                        'name' => $media->name,
                        'url' => $media->url,
                        'type' => $media->type,
                    ];
                })->values(),
            ],
        ]);
    }

    public function recall(Request $request, Conversation $conversation, Message $message)
    {
        $user = $request->user();

        if (
            ! $conversation->users()->where('users.id', $user->id)->exists()
            || $message->conversation_id !== $conversation->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền thu hồi tin nhắn này.',
            ], 403);
        }

        if ($message->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ được thu hồi tin nhắn của chính bạn.',
            ], 403);
        }

        DB::transaction(function () use ($message) {
            $message->update([
                'content' => null,
                'type' => 'recalled',
            ]);
            $message->medias()->delete();
        });

        $message->load('medias');
        event(new \App\Events\MessageSent($message));

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $message->id,
                'user_id' => $message->user_id,
                'content' => $message->content,
                'type' => $message->type,
                'created_at' => $message->created_at,
                'medias' => [],
            ],
        ]);
    }

    private function detectMediaType(?string $mime): string
    {
        if (! $mime) {
            return 'file';
        }

        if (Str::startsWith($mime, 'image/')) {
            return 'image';
        }

        if (Str::startsWith($mime, 'video/')) {
            return 'video';
        }

        return 'file';
    }
}

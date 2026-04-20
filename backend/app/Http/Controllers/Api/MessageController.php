<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\MessageReadUpdated;
use App\Events\MessageSent;
use App\Jobs\GenerateChatboxReplyJob;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageMedia;
use App\Models\MessageProduct;
use App\Models\MessageRead;
use App\Models\Tier;
use App\Models\User;
use App\Services\ChatboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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

        $admin = $this->resolveActiveAdmin();

        if (! $admin) {
            return response()->json([
                'success' => false,
                'message' => 'Chưa cấu hình tài khoản admin để nhận tin nhắn.',
            ], 404);
        }

        $conversation = $this->findOrCreatePrivateConversation($user, $admin);

        return response()->json([
            'success' => true,
            'conversation' => $this->buildConversationPayload($conversation, $user),
        ]);
    }

    public function ensureChatboxConversation(Request $request, ChatboxService $chatbox)
    {
        $user = $request->user();

        if ($user->role !== 'user') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ khách hàng mới sử dụng được trợ lý AI.',
            ], 403);
        }

        $conversation = $chatbox->ensureConversationForUser($user);

        return response()->json([
            'success' => true,
            'conversation' => $chatbox->buildConversationPayload($conversation, $user),
        ]);
    }

    public function customerInbox(Request $request, ChatboxService $chatbox)
    {
        $user = $request->user();

        if ($user->role !== 'user') {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ khách hàng mới sử dụng được hộp thư này.',
            ], 403);
        }

        $items = [];

        $admin = $this->resolveActiveAdmin();
        if ($admin) {
            $conversation = $this->findOrCreatePrivateConversation($user, $admin);
            $items[] = $this->buildInboxItem($conversation, $user);
        }

        $chatboxConversation = $chatbox->ensureConversationForUser($user);
        $items[] = $this->buildInboxItem($chatboxConversation, $user);

        usort($items, function (array $a, array $b) {
            $aTime = strtotime((string) ($a['updated_at'] ?? '')) ?: 0;
            $bTime = strtotime((string) ($b['updated_at'] ?? '')) ?: 0;

            if ($aTime === $bTime) {
                return strcmp((string) ($a['kind'] ?? ''), (string) ($b['kind'] ?? ''));
            }

            return $bTime <=> $aTime;
        });

        return response()->json([
            'success' => true,
            'conversations' => $items,
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

        return response()->json([
            'success' => true,
            'conversation' => $this->buildConversationPayload($conversation, $admin),
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
            ->with(['medias', 'reads', 'suggestedProducts.product.images', 'suggestedProducts.product.category', 'suggestedProducts.product.prices.tier', 'suggestedProducts.product.colors'])
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
                    'products' => $this->mapSuggestedProducts($message, $user),
                ];
            });

        // mark as read for current user (only incoming)
        $toInsert = [];
        $updatedMessages = [];
        foreach ($conversation->messages as $message) {
            $already = $message->reads->firstWhere('user_id', $user->id);
            if ($message->user_id !== $user->id && ! $already) {
                $toInsert[] = [
                    'message_id' => $message->id,
                    'user_id' => $user->id,
                    'read_at' => now(),
                ];
                $updatedMessages[] = [
                    'id' => $message->id,
                    'read_by_user_ids' => array_values(array_unique([
                        ...$message->reads->pluck('user_id')->map(fn ($id) => (int) $id)->all(),
                        (int) $user->id,
                    ])),
                ];
            }
        }
        if ($toInsert) {
            MessageRead::insertOrIgnore($toInsert);
            event(new MessageReadUpdated($conversation, (int) $user->id, $updatedMessages));
        }

        return response()->json([
            'success' => true,
            'conversation' => $this->buildConversationPayload($conversation, $user),
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
            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $files = $request->file('files', []);
        $productIds = collect($validated['product_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if (empty($validated['content']) && empty($files) && $productIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Nội dung tin nhắn trống.',
            ], 422);
        }

        $type = count($files) ? 'media' : 'text';

        /** @var Message $message */
        $message = DB::transaction(function () use ($conversation, $user, $validated, $files, $type, $productIds) {
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

            foreach ($productIds as $productId) {
                MessageProduct::query()->create([
                    'message_id' => $message->id,
                    'product_id' => $productId,
                ]);
            }

            return $message;
        });

        $message->load([
            'medias',
            'reads',
            'suggestedProducts.product.images',
            'suggestedProducts.product.category',
            'suggestedProducts.product.prices.tier',
            'suggestedProducts.product.colors',
        ]);

        event(new MessageSent($message));

        if ($conversation->type === 'chatbox' && $user->role === 'user') {
            GenerateChatboxReplyJob::dispatchAfterResponse((int) $message->id);
        }

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
                'products' => $this->mapSuggestedProducts($message, $user),
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

    private function resolveActiveAdmin(): ?User
    {
        return User::query()
            ->with('profile')
            ->where('role', 'admin')
            ->where('status', 'actived')
            ->orderBy('id')
            ->first();
    }

    private function findOrCreatePrivateConversation(User $left, User $right): Conversation
    {
        $conversation = Conversation::query()
            ->where('type', 'private')
            ->whereHas('users', fn ($q) => $q->where('users.id', $left->id))
            ->whereHas('users', fn ($q) => $q->where('users.id', $right->id))
            ->first();

        if ($conversation) {
            return $conversation;
        }

        return DB::transaction(function () use ($left, $right) {
            $conversation = Conversation::query()->create([
                'type' => 'private',
                'name' => null,
            ]);

            $conversation->users()->attach([$left->id, $right->id]);

            return $conversation;
        });
    }

    private function buildConversationPayload(Conversation $conversation, User $viewer): array
    {
        $conversation->loadMissing('users.profile');

        if ($conversation->type === 'chatbox') {
            $partner = $conversation->users->firstWhere('role', 'bot');

            return [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'kind' => 'chatbox_advice',
                'name' => $conversation->name ?: 'Trợ lý AI',
                'thumb' => $conversation->thumb,
                'bot' => [
                    'id' => $partner?->id,
                    'name' => $conversation->name ?: 'Trợ lý AI',
                    'avatar' => null,
                ],
                'partner' => [
                    'id' => $partner?->id,
                    'name' => $conversation->name ?: 'Trợ lý AI',
                    'avatar' => null,
                    'role' => 'bot',
                ],
            ];
        }

        $partner = $conversation->users->firstWhere('id', '!=', $viewer->id);

        $partnerPayload = [
            'id' => $partner?->id,
            'name' => $partner?->profile?->name ?? $partner?->username,
            'avatar' => $partner?->profile?->avatar,
            'role' => $partner?->role,
        ];

        $payload = [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'kind' => 'admin_support',
            'name' => $conversation->name,
            'thumb' => $conversation->thumb,
            'partner' => $partnerPayload,
        ];

        if ($viewer->role === 'user') {
            $payload['admin'] = Arr::except($partnerPayload, ['role']);
        } else {
            $payload['user'] = Arr::except($partnerPayload, ['role']);
        }

        return $payload;
    }

    private function buildInboxItem(Conversation $conversation, User $viewer): array
    {
        $conversation->loadMissing(['users.profile', 'messages' => fn ($q) => $q->latest()->limit(1)]);
        $lastMessage = $conversation->messages->first();
        $payload = $this->buildConversationPayload($conversation, $viewer);
        $unread = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $viewer->id)
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $viewer->id))
            ->count();

        $preview = null;
        if ($lastMessage) {
            $preview = $lastMessage->type === 'media'
                ? 'Đã gửi một tệp'
                : ($lastMessage->type === 'recalled'
                    ? 'Tin nhắn đã bị thu hồi'
                    : $lastMessage->content);
        }

        return [
            ...$payload,
            'last_message' => $preview,
            'updated_at' => optional($lastMessage?->created_at)?->toISOString(),
            'unread' => $unread,
        ];
    }

    private function mapSuggestedProducts(Message $message, ?User $viewer = null): array
    {
        $tierId = $this->resolveEffectiveTierId($viewer);

        return $message->suggestedProducts->map(function (MessageProduct $link) use ($tierId) {
            $product = $link->product;
            if (! $product) {
                return null;
            }

            $pricing = $this->resolveUnitPriceWithMinQty($product->prices, $tierId, 1);
            $price = (float) ($pricing['unit_price'] ?? 0);

            return [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'category' => (string) ($product->category?->name ?? 'Khác'),
                'image' => (string) ($product->images->first()?->url ?? ''),
                'price' => $price > 0 ? $price : null,
                'url' => '/products/' . (int) $product->id,
                'unit' => (string) ($product->unit ?? ''),
                'colors' => $product->colors->map(fn ($color) => [
                    'id' => (int) $color->id,
                    'color_name' => (string) ($color->color_name ?? 'Mặc định'),
                ])->values()->all(),
            ];
        })->filter()->values()->all();
    }

    private function resolveEffectiveTierId(?User $user): ?int
    {
        if (! $user) {
            return null;
        }

        $user->loadMissing(['dealerProfile', 'profile']);

        $dealerProfile = $user->dealerProfile;
        if (
            $dealerProfile
            && (string) ($dealerProfile->status ?? '') === 'accepted'
            && (int) ($dealerProfile->tier_id ?? 0) > 0
        ) {
            return (int) $dealerProfile->tier_id;
        }

        if ((int) ($user->tier_id ?? 0) > 0) {
            return (int) $user->tier_id;
        }

        if ((int) ($user->profile?->tier ?? 0) > 0) {
            return (int) $user->profile->tier;
        }

        return null;
    }

    private function resolveUnitPriceWithMinQty($prices, ?int $tierId, int $quantity): array
    {
        $rows = collect($prices)->sortBy('min_quantity')->values();
        if ($rows->isEmpty()) {
            return [
                'unit_price' => 0,
                'min_quantity' => 1,
            ];
        }

        $tierRows = $tierId == null
            ? collect()
            : $rows->where('tier_id', $tierId)->values();

        if ($tierRows->isEmpty()) {
            $defaultTierId = (int) (Tier::query()->where('default', 1)->value('id') ?? 0);
            $retailRows = $defaultTierId > 0
                ? $rows->where('tier_id', $defaultTierId)->values()
                : collect();

            if ($retailRows->isNotEmpty()) {
                $tierRows = $retailRows;
            }
        }

        if ($tierRows->isEmpty()) {
            $firstTierId = (int) ($rows->first()->tier_id ?? 0);
            $tierRows = $rows->where('tier_id', $firstTierId)->values();
        }

        $applied = $tierRows->first();
        foreach ($tierRows as $row) {
            if ((int) ($row->min_quantity ?? 0) <= $quantity) {
                $applied = $row;
            }
        }

        return [
            'unit_price' => (float) ($applied->price ?? 0),
            'min_quantity' => (int) ($applied->min_quantity ?? 1),
        ];
    }
}

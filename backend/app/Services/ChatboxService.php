<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Evaluate;
use App\Models\Message;
use App\Models\MessageProduct;
use App\Models\MessageRead;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ChatboxService
{
    public function __construct(
        private AiSearchClient $ai,
        private OpenAiChatService $openAi,
    ) {
    }

    public function ensureConversationForUser(User $user): Conversation
    {
        $bot = $this->ensureBotUser();

        $conversation = Conversation::query()
            ->where('type', 'chatbox')
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->whereHas('users', fn ($q) => $q->where('users.id', $bot->id))
            ->first();

        if ($conversation) {
            return $conversation;
        }

        return DB::transaction(function () use ($user, $bot) {
            $conversation = Conversation::query()->create([
                'type' => 'chatbox',
                'name' => 'Trợ lý AI',
                'thumb' => null,
            ]);

            $conversation->users()->attach([$user->id, $bot->id]);

            return $conversation;
        });
    }

    public function ensureBotUser(): User
    {
        $username = (string) config('services.openai.chatbox_bot_username', 'ai_chatbox');
        $email = (string) config('services.openai.chatbox_bot_email', 'ai-chatbox@local.study-store');

        $bot = User::query()->where('role', 'bot')->where('email', $email)->first();
        if ($bot) {
            if ($bot->status !== 'actived' || $bot->username !== $username) {
                $bot->forceFill([
                    'status' => 'actived',
                    'username' => $username,
                ])->save();
            }

            return $bot;
        }

        return User::query()->create([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make(Str::random(40)),
            'status' => 'actived',
            'role' => 'bot',
        ]);
    }

    public function buildConversationPayload(Conversation $conversation, User $viewer): array
    {
        $conversation->loadMissing('users.profile');

        if ($conversation->type === 'chatbox') {
            $bot = $conversation->users->firstWhere('role', 'bot');

            return [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'kind' => 'chatbox_advice',
                'name' => $conversation->name ?: 'Trợ lý AI',
                'thumb' => $conversation->thumb,
                'bot' => [
                    'id' => $bot?->id,
                    'name' => $conversation->name ?: 'Trợ lý AI',
                    'avatar' => null,
                ],
                'partner' => [
                    'id' => $bot?->id,
                    'name' => $conversation->name ?: 'Trợ lý AI',
                    'avatar' => null,
                    'role' => 'bot',
                ],
            ];
        }

        $partner = $conversation->users->firstWhere('id', '!=', $viewer->id);

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'kind' => 'admin_support',
            'name' => $conversation->name,
            'thumb' => $conversation->thumb,
            'admin' => [
                'id' => $partner?->id,
                'name' => $partner?->profile?->name ?? $partner?->username,
                'avatar' => $partner?->profile?->avatar,
            ],
            'partner' => [
                'id' => $partner?->id,
                'name' => $partner?->profile?->name ?? $partner?->username,
                'avatar' => $partner?->profile?->avatar,
                'role' => $partner?->role,
            ],
        ];
    }

    public function replyToMessage(Message $message): void
    {
        $message->loadMissing(['conversation.users.profile', 'user']);

        $conversation = $message->conversation;
        if (! $conversation || $conversation->type !== 'chatbox') {
            return;
        }

        $customer = $conversation->users->firstWhere('role', 'user');
        $bot = $conversation->users->firstWhere('role', 'bot') ?? $this->ensureBotUser();

        if (! $customer || ! $bot) {
            return;
        }

        MessageRead::query()->firstOrCreate([
            'message_id' => $message->id,
            'user_id' => $bot->id,
        ], [
            'read_at' => now(),
        ]);

        ['text' => $advice, 'products' => $products] = $this->buildAdvice($conversation, $message->content, $customer);

        $reply = Message::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $bot->id,
            'content' => $advice,
            'type' => 'text',
        ]);

        foreach ($products->take(3) as $product) {
            MessageProduct::query()->create([
                'message_id' => $reply->id,
                'product_id' => $product->id,
            ]);
        }

        MessageRead::query()->firstOrCreate([
            'message_id' => $reply->id,
            'user_id' => $bot->id,
        ], [
            'read_at' => now(),
        ]);

        $reply->load(['medias', 'reads', 'conversation.users']);

        event(new MessageSent($reply));
    }

    private function buildAdvice(Conversation $conversation, ?string $latestUserMessage, User $customer): array
    {
        $question = trim((string) $latestUserMessage);

        if ($question === '') {
            return [
                'text' => 'Mình chưa thấy nội dung câu hỏi. Bạn hãy mô tả nhu cầu như loại sản phẩm, mục đích dùng hoặc mức giá mong muốn để mình gợi ý chính xác hơn.',
                'products' => collect(),
            ];
        }

        $semanticResults = collect($this->ai->semanticSearch($question, 8));
        $products = $this->loadProductsFromSemanticResults($semanticResults);
        $history = $this->buildHistoryText($conversation, 8);
        $productContext = $this->buildProductContext($products);

        $systemPrompt = implode("\n", [
            'Bạn là trợ lý tư vấn sản phẩm cho cửa hàng văn phòng phẩm.',
            'Bạn chỉ được dùng thông tin trong ngữ cảnh sản phẩm được cung cấp.',
            'Không được bịa giá, tồn kho, màu, đánh giá hoặc chương trình khuyến mãi.',
            'Nếu không đủ dữ liệu, hãy nói rõ và hỏi thêm một câu ngắn để làm rõ nhu cầu.',
            'Trả lời bằng tiếng Việt tự nhiên, ngắn gọn, hữu ích.',
            'Nếu có sản phẩm phù hợp, hãy nêu tối đa 3 lựa chọn nổi bật nhất và giải thích ngắn vì sao phù hợp.',
        ]);

        $customerName = $customer->profile?->name ?: $customer->username;

        $userPrompt = implode("\n\n", [
            "Khách hàng: {$customerName}",
            "Câu hỏi mới nhất:\n{$question}",
            "Lịch sử hội thoại gần đây:\n{$history}",
            "Ngữ cảnh sản phẩm truy hồi được:\n{$productContext}",
        ]);

        $reply = $this->openAi->generateAdvice($systemPrompt, $userPrompt);

        if ($reply) {
            return [
                'text' => trim($reply),
                'products' => $products,
            ];
        }

        return [
            'text' => $this->buildFallbackReply($products, $question),
            'products' => $products,
        ];
    }

    private function loadProductsFromSemanticResults(Collection $semanticResults): Collection
    {
        $ids = $semanticResults->pluck('id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        if (empty($ids)) {
            return collect();
        }

        $scoreById = $semanticResults->pluck('score', 'id');

        $products = Product::query()
            ->with(['category', 'prices', 'colors', 'images'])
            ->whereIn('id', $ids)
            ->whereIn('products.id', function ($sub) {
                $sub->from('warehouse_details')
                    ->selectRaw('product_id')
                    ->where('status', 'actived')
                    ->groupBy('product_id')
                    ->havingRaw('SUM(quantity) > 0');
            })
            ->orderByRaw('FIELD(id, ' . implode(',', array_map('intval', $ids)) . ')')
            ->get()
            ->map(function (Product $product) use ($scoreById) {
                $product->setAttribute('semantic_score', (float) ($scoreById[(string) $product->id] ?? $scoreById[$product->id] ?? 0));
                $product->setAttribute('rating', round((float) (Evaluate::query()->where('product_id', $product->id)->avg('rating') ?? 0), 1));
                $product->setAttribute('sold', (int) OrderDetail::query()
                    ->join('orders', 'orders.id', '=', 'order_details.order_id')
                    ->where('order_details.product_id', $product->id)
                    ->where('orders.status', 'completed')
                    ->count('order_details.order_id'));
                $product->setAttribute('stock_quantity', (int) DB::table('warehouse_details')
                    ->where('product_id', $product->id)
                    ->where('status', 'actived')
                    ->sum('quantity'));

                return $product;
            });

        return collect($products);
    }

    private function buildHistoryText(Conversation $conversation, int $limit = 8): string
    {
        $messages = $conversation->messages()
            ->with('user')
            ->where('type', '!=', 'recalled')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        if ($messages->isEmpty()) {
            return '- Chưa có lịch sử hội thoại trước đó.';
        }

        return $messages->map(function (Message $message) {
            $speaker = match ($message->user?->role) {
                'bot' => 'Trợ lý AI',
                'admin' => 'Admin',
                default => 'Khách hàng',
            };

            $content = trim((string) $message->content);
            if ($content === '') {
                $content = '[Tin nhắn không có văn bản hoặc chỉ có tệp đính kèm]';
            }

            return "- {$speaker}: {$content}";
        })->implode("\n");
    }

    private function buildProductContext(Collection $products): string
    {
        if ($products->isEmpty()) {
            return '- Không tìm thấy sản phẩm phù hợp rõ ràng trong dữ liệu hiện có.';
        }

        return $products->take(6)->map(function (Product $product) {
            $price = $product->prices->min('price');
            $colors = $product->colors->pluck('color_name')->filter()->take(5)->implode(', ');
            $lines = [
                "- [#{$product->id}] {$product->name}",
                '  Danh mục: ' . ($product->category?->name ?? 'Khác'),
                '  Giá tham khảo: ' . ($price !== null ? number_format((float) $price, 0, ',', '.') . ' đ' : 'chưa có'),
                '  Tồn kho khả dụng: ' . (int) ($product->stock_quantity ?? 0),
                '  Đánh giá trung bình: ' . (float) ($product->rating ?? 0),
                '  Lượt mua hoàn tất: ' . (int) ($product->sold ?? 0),
                '  Màu sắc: ' . ($colors !== '' ? $colors : 'chưa rõ'),
            ];

            if (filled($product->des)) {
                $lines[] = '  Mô tả: ' . Str::limit(trim((string) $product->des), 220);
            }

            return implode("\n", $lines);
        })->implode("\n");
    }

    private function buildFallbackReply(Collection $products, string $question): string
    {
        if ($products->isEmpty()) {
            return "Mình chưa tìm được sản phẩm thật sự sát với nhu cầu \"{$question}\" trong dữ liệu hiện có. Bạn có thể nói rõ hơn về loại sản phẩm, công dụng hoặc tầm giá để mình gợi ý chính xác hơn.";
        }

        $lines = [
            "Mình gợi ý một vài sản phẩm gần với nhu cầu \"{$question}\":",
        ];

        foreach ($products->take(3) as $product) {
            $price = $product->prices->min('price');
            $line = "- {$product->name}";
            if ($product->category?->name) {
                $line .= " ({$product->category->name})";
            }
            if ($price !== null) {
                $line .= ' - khoảng ' . number_format((float) $price, 0, ',', '.') . ' đ';
            }
            $lines[] = $line;
        }

        $lines[] = 'Nếu bạn muốn, mình có thể gợi ý tiếp theo mục đích dùng, màu sắc hoặc mức giá.';

        return implode("\n", $lines);
    }
}

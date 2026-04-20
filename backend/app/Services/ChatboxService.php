<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Models\Cart;
use App\Models\Conversation;
use App\Models\Discount;
use App\Models\Evaluate;
use App\Models\Message;
use App\Models\MessageProduct;
use App\Models\MessageRead;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderDiscount;
use App\Models\Product;
use App\Models\Tier;
use App\Models\User;
use Carbon\Carbon;
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

        $intents = $this->routeIntents($question);
        $products = collect();
        if ($intents['product']) {
            $semanticResults = collect($this->ai->semanticSearch($question, 8));
            $products = $this->loadProductsFromSemanticResults($semanticResults);
        }

        $history = $this->buildHistoryText($conversation, 8);
        $contextSections = [];

        if ($intents['product']) {
            $contextSections[] = [
                'label' => 'Ngữ cảnh sản phẩm',
                'content' => $this->buildProductContext($products, $customer),
            ];
        }
        if ($intents['order']) {
            $contextSections[] = [
                'label' => 'Ngữ cảnh đơn hàng',
                'content' => $this->buildOrderContext($customer, $question),
            ];
        }
        if ($intents['cart']) {
            $contextSections[] = [
                'label' => 'Ngữ cảnh giỏ hàng',
                'content' => $this->buildCartContext($customer),
            ];
        }
        if ($intents['promotion']) {
            $contextSections[] = [
                'label' => 'Ngữ cảnh khuyến mãi',
                'content' => $this->buildPromotionContext($question),
            ];
        }
        if ($intents['account']) {
            $contextSections[] = [
                'label' => 'Ngữ cảnh tài khoản',
                'content' => $this->buildAccountContext($customer),
            ];
        }
        if ($intents['policy']) {
            $contextSections[] = [
                'label' => 'Ngữ cảnh chính sách',
                'content' => $this->buildPolicyContext($question),
            ];
        }
        if ($intents['system']) {
            $contextSections[] = [
                'label' => 'Ngữ cảnh hệ thống',
                'content' => $this->buildSystemInfoContext(),
            ];
        }

        $systemPrompt = implode("\n", [
            'Bạn là trợ lý AI cho cửa hàng văn phòng phẩm.',
            'Bạn chỉ có vai trò tư vấn và cung cấp thông tin, không có quyền thay đổi dữ liệu hay thao tác hộ khách hàng trên hệ thống.',
            'Bạn không được nói hoặc ngụ ý rằng bạn có thể cập nhật giỏ hàng, đặt đơn hàng, hủy đơn, áp mã giảm giá, đổi thông tin tài khoản, đổi địa chỉ, đổi phương thức thanh toán hay thực hiện bất kỳ thao tác quản trị/hệ thống nào.',
            'Khi khách hàng yêu cầu thao tác, hãy hướng dẫn họ tự thực hiện trên hệ thống hoặc khéo gợi ý liên hệ quản lý/hỗ trợ để được giúp đỡ.',
            'Bạn chỉ được dùng thông tin có trong các ngữ cảnh được cung cấp.',
            'Ngữ cảnh có thể gồm sản phẩm, đơn hàng cá nhân, giỏ hàng, tài khoản, khuyến mãi, chính sách và thông tin hệ thống.',
            'Chỉ được trả lời dữ liệu cá nhân của đúng khách hàng trong cuộc trò chuyện hiện tại.',
            'Không được bịa giá, tồn kho, màu, đánh giá, đơn hàng, khuyến mãi hay chính sách.',
            'Nếu không đủ dữ liệu, hãy nói rõ, hỏi thêm một câu ngắn để làm rõ nhu cầu và khéo gợi ý khách hàng nhắn quản lý/hỗ trợ của hệ thống để được hỗ trợ thêm khi cần.',
            'Trả lời bằng tiếng Việt tự nhiên, ngắn gọn, hữu ích.',
            'Nếu có sản phẩm phù hợp, hãy nêu tối đa 3 lựa chọn nổi bật nhất và giải thích ngắn vì sao phù hợp.',
        ]);

        $customerName = $customer->profile?->name ?: $customer->username;

        $userPrompt = implode("\n\n", [
            "Khách hàng: {$customerName}",
            "Câu hỏi mới nhất:\n{$question}",
            "Lịch sử hội thoại gần đây:\n{$history}",
            $this->buildPromptContexts($contextSections),
        ]);

        $reply = $this->openAi->generateAdvice($systemPrompt, $userPrompt);

        if ($reply) {
            return [
                'text' => trim($reply),
                'products' => $products,
            ];
        }

        return [
            'text' => $this->buildFallbackReply($products, $question, $customer),
            'products' => $products,
        ];
    }

    private function routeIntents(string $question): array
    {
        $normalized = $this->normalizeQuestion($question);
        $nonProduct = false;

        $promotion = $this->containsAny($normalized, [
            'khuyen mai', 'uu dai', 'giam gia', 'voucher', 'ma giam gia', 'discount', 'coupon',
        ]);
        $order = $this->containsAny($normalized, [
            'don hang', 'don cua toi', 'don toi', 'xem don', 'kiem tra don', 'tra cuu don',
            'don gan nhat', 'order', 'van chuyen', 'giao hang', 'trang thai don', 'ma don',
            'ma so don', 'shipping', 'don'
        ]);
        $cart = $this->containsAny($normalized, [
            'gio hang', 'cart', 'san pham da them', 'trong gio', 'gio', 'xem gio', 'kiem tra gio', 'tra cuu gio',
        ]);
        $account = $this->containsAny($normalized, [
            'tai khoan', 'ho so', 'thong tin cua toi', 'thong tin tai khoan', 'hang thanh vien', 'tier',
        ]);
        $policy = $this->containsAny($normalized, [
            'chinh sach', 'doi tra', 'bao hanh', 'thanh toan', 'van chuyen nhu the nao', 'faq', 'huong dan',
        ]);
        $system = $this->containsAny($normalized, [
            'he thong', 'ung dung nay', 'duoc tao boi ai', 'ai tao', 'muc dich', 'de lam gi', 'gioi thieu',
        ]);
        $nonProduct = $promotion || $order || $cart || $account || $policy || $system;

        $product = ! $nonProduct && $this->containsAny($normalized, [
            'san pham', 'but', 'vo', 'tap', 'giay', 'keo', 'thuoc', 'compa', 'mau', 'van phong pham',
            'tim giup', 'goi y', 'tu van', 'mua', 'gia', 'mau sac',
        ]);

        return [
            'product' => $product,
            'promotion' => $promotion,
            'order' => $order,
            'cart' => $cart,
            'account' => $account,
            'policy' => $policy,
            'system' => $system,
        ];
    }

    private function buildOrderContext(User $customer, string $question): string
    {
        $query = Order::query()
            ->with(['orderDetails.product', 'orderDiscounts.discount', 'deliveryInfo', 'payment'])
            ->where('user_id', $customer->id)
            ->latest('id');

        $orderId = $this->extractRequestedOrderId($question);
        if ($orderId !== null) {
            $query->where('id', $orderId);
        }

        $orders = $query->get();
        if ($orders->isEmpty()) {
            return '- Khách hàng chưa có đơn hàng nào trong hệ thống.';
        }

        $summaryLines = [
            '- Tổng quan đơn hàng',
            '  Tổng số đơn: ' . $orders->count(),
            '  Chờ xác nhận: ' . $orders->where('status', 'pending')->count(),
            '  Đang giao: ' . $orders->where('status', 'shipping')->count(),
            '  Hoàn tất: ' . $orders->where('status', 'completed')->count(),
            '  Đã hủy: ' . $orders->where('status', 'cancelled')->count(),
            '  Bị từ chối: ' . $orders->where('status', 'rejected')->count(),
        ];

        $orderLines = $orders->map(function (Order $order) {
            $productSubtotal = (float) $order->orderDetails->sum(fn ($row) => ((float) $row->price) * ((int) $row->quantity));
            $discountTotal = (float) $order->orderDiscounts->sum(fn ($row) => (float) ($row->price ?? 0));
            $shippingFee = 30000.0;
            $total = max(0, $productSubtotal - $discountTotal + $shippingFee);
            $items = $order->orderDetails
                ->map(fn ($row) => $row->product?->name ? $row->product->name . ' x' . (int) $row->quantity : null)
                ->filter()
                ->take(4)
                ->implode(', ');
            $discounts = $order->orderDiscounts
                ->map(fn ($row) => $row->discount?->des ?: null)
                ->filter()
                ->take(3)
                ->implode(', ');

            $lines = [
                "- Đơn #{$order->id}",
                '  Trạng thái: ' . $this->mapOrderStatus((string) $order->status),
                '  Tổng tiền tạm tính: ' . number_format($total, 0, ',', '.') . ' đ',
                '  Thanh toán: ' . ($order->payment?->name ?: 'chưa rõ'),
                '  Sản phẩm: ' . ($items !== '' ? $items : 'chưa rõ'),
                '  Địa chỉ nhận: ' . ($order->deliveryInfo?->address ?: 'chưa rõ'),
            ];
            if ($discounts !== '') {
                $lines[] = '  Khuyến mãi áp dụng: ' . $discounts;
            }

            return implode("\n", $lines);
        })->implode("\n");

        return implode("\n", $summaryLines) . "\n" . $orderLines;
    }

    private function extractRequestedOrderId(string $question): ?int
    {
        $normalized = $this->normalizeQuestion($question);
        $patterns = [
            '/\bma\s*don\s*#?\s*(\d{1,10})\b/',
            '/\border\s*#?\s*(\d{1,10})\b/',
            '/\bdon\s*hang\s*#?\s*(\d{1,10})\b/',
            '/\bdon\s*#\s*(\d{1,10})\b/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalized, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    private function buildPromotionContext(string $question): string
    {
        $today = Carbon::today();
        $query = Discount::query()
            ->with('category')
            ->where('status', 'actived')
            ->whereDate('start_at', '<=', $today)
            ->whereDate('end_at', '>=', $today)
            ->orderByDesc('percent')
            ->orderBy('end_at');

        $normalized = $this->normalizeQuestion($question);
        if ($this->containsAny($normalized, ['but', 'tap', 'vo', 'giay', 'keo', 'thuoc'])) {
            $query->whereHas('category', function ($q) use ($question) {
                $q->whereRaw('LOWER(name) like ?', ['%' . Str::lower($question) . '%']);
            });
        }

        $discounts = $query->limit(5)->get();
        if ($discounts->isEmpty()) {
            return '- Hiện chưa có khuyến mãi đang hoạt động phù hợp rõ ràng trong dữ liệu.';
        }

        return $discounts->map(function (Discount $discount) {
            return implode("\n", [
                "- Khuyến mãi #{$discount->id}",
                '  Nội dung: ' . (trim((string) $discount->des) !== '' ? trim((string) $discount->des) : 'Khuyến mãi đang hoạt động'),
                '  Mức giảm: ' . (float) ($discount->percent ?? 0) . '%',
                '  Danh mục: ' . ($discount->category?->name ?: 'tất cả'),
                '  Hiệu lực: ' . ($discount->start_at ?: 'chưa rõ') . ' đến ' . ($discount->end_at ?: 'chưa rõ'),
            ]);
        })->implode("\n");
    }

    private function buildPolicyContext(string $question): string
    {
        $knowledge = (array) config('chatbox.policy', []);
        if (empty($knowledge)) {
            return '- Chưa cấu hình dữ liệu chính sách/FAQ.';
        }

        $normalized = $this->normalizeQuestion($question);
        $matched = collect($knowledge)->filter(function ($item, $key) use ($normalized) {
            return str_contains($normalized, $this->normalizeQuestion((string) $key))
                || str_contains($normalized, $this->normalizeQuestion((string) ($item['title'] ?? '')));
        });

        $items = $matched->isNotEmpty() ? $matched : collect($knowledge)->take(3);

        return $items->map(function ($item, $key) {
            $title = is_array($item) ? ($item['title'] ?? $key) : $key;
            $content = is_array($item) ? ($item['content'] ?? '') : (string) $item;
            return "- {$title}: {$content}";
        })->implode("\n");
    }

    private function buildAccountContext(User $customer): string
    {
        $customer->loadMissing(['profile', 'tier', 'deliveryInfos']);

        $totalOrders = Order::query()->where('user_id', $customer->id)->count();
        $completedOrders = Order::query()->where('user_id', $customer->id)->where('status', 'completed')->count();
        $defaultAddress = $customer->deliveryInfos->firstWhere('default', true)?->address
            ?: $customer->deliveryInfos->first()?->address;

        return implode("\n", [
            '- Tài khoản khách hàng',
            '  Tên: ' . ($customer->profile?->name ?: $customer->username),
            '  Email: ' . ($customer->email ?: 'chưa rõ'),
            '  Số điện thoại: ' . ($customer->profile?->phone ?: 'chưa rõ'),
            '  Hạng thành viên: ' . ($customer->tier?->name ?: 'chưa rõ'),
            '  Tổng số đơn hàng: ' . $totalOrders,
            '  Đơn hoàn tất: ' . $completedOrders,
            '  Địa chỉ mặc định: ' . ($defaultAddress ?: 'chưa có'),
        ]);
    }

    private function buildCartContext(User $customer): string
    {
        $tierId = $this->resolveEffectiveTierId($customer);
        $cart = Cart::query()
            ->with(['cartDetails.product.category', 'cartDetails.product.prices.tier', 'cartDetails.color'])
            ->where('user_id', $customer->id)
            ->first();

        if (! $cart || $cart->cartDetails->isEmpty()) {
            return '- Giỏ hàng hiện đang trống.';
        }

        $lines = ['- Giỏ hàng hiện tại'];
        $subtotal = 0.0;
        $totalItems = 0;
        foreach ($cart->cartDetails as $detail) {
            $qty = max(1, (int) $detail->quantity);
            $totalItems += $qty;
            $pricing = $this->resolveUnitPriceWithMinQty($detail->product?->prices, $tierId, $qty);
            $price = (float) ($pricing['unit_price'] ?? 0);
            $minQty = (int) ($pricing['min_quantity'] ?? 1);
            $lineTotal = $price * $qty;
            $subtotal += $lineTotal;
            $color = $detail->color?->color_name ? ' - màu ' . $detail->color->color_name : '';
            $priceText = $price > 0
                ? ' - đơn giá ' . number_format($price, 0, ',', '.') . ' đ'
                    . ' - thành tiền ' . number_format($lineTotal, 0, ',', '.') . ' đ'
                    . ($minQty > 1 ? ' (áp dụng từ SL ' . $minQty . ')' : '')
                : '';
            $lines[] = '  ' . ($detail->product?->name ?: 'Sản phẩm') . ' x' . $qty . $color . $priceText;
        }
        $lines[] = '  Tạm tính: ' . number_format($subtotal, 0, ',', '.') . ' đ';

        array_splice($lines, 1, 0, [
            '  Số dòng sản phẩm: ' . $cart->cartDetails->count(),
            '  Tổng số lượng: ' . $totalItems,
        ]);

        return implode("\n", $lines);
    }

    private function buildSystemInfoContext(): string
    {
        $about = (array) config('chatbox.about', []);
        $functions = implode(', ', (array) ($about['system_functions'] ?? []));

        return implode("\n", [
            '- Thông tin hệ thống',
            '  Tên hệ thống: ' . (($about['name'] ?? null) ?: 'Trợ lý AI cửa hàng văn phòng phẩm'),
            '  Đơn vị xây dựng: ' . (($about['owner'] ?? null) ?: 'chưa cấu hình'),
            '  Ý nghĩa học thuật: ' . (($about['academic_purpose'] ?? null) ?: 'chưa cấu hình'),
            '  Mục đích hệ thống: ' . (($about['purpose'] ?? null) ?: 'Hỗ trợ tư vấn sản phẩm và giải đáp thông tin cho khách hàng'),
            '  Chức năng hệ thống: ' . ($functions !== '' ? $functions : 'chưa cấu hình'),
            '  Phạm vi hỗ trợ: ' . implode(', ', (array) ($about['supported_topics'] ?? ['sản phẩm', 'đơn hàng', 'giỏ hàng', 'tài khoản', 'khuyến mãi', 'chính sách'])),
        ]);
    }

    private function buildPromptContexts(array $contextSections): string
    {
        $usable = collect($contextSections)
            ->filter(fn ($section) => filled($section['content'] ?? null))
            ->map(fn ($section) => ($section['label'] ?? 'Ngữ cảnh') . ":\n" . ($section['content'] ?? ''));

        if ($usable->isEmpty()) {
            return 'Chưa có ngữ cảnh dữ liệu phù hợp nào ngoài lịch sử hội thoại.';
        }

        return $usable->implode("\n\n");
    }

    private function normalizeQuestion(string $text): string
    {
        return Str::lower(trim(Str::ascii($text)));
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $this->normalizeQuestion($needle))) {
                return true;
            }
        }

        return false;
    }

    private function mapOrderStatus(string $status): string
    {
        return match ($status) {
            'pending' => 'Chờ xác nhận',
            'shipping' => 'Đang giao',
            'completed' => 'Hoàn tất',
            'cancelled' => 'Đã hủy',
            'rejected' => 'Bị từ chối',
            default => $status,
        };
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

    private function loadProductsFromSemanticResults(Collection $semanticResults): Collection
    {
        $ids = $semanticResults->pluck('id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        if (empty($ids)) {
            return collect();
        }

        $scoreById = $semanticResults->pluck('score', 'id');

        $products = Product::query()
            ->with(['category', 'prices.tier', 'colors', 'images'])
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

    private function buildProductContext(Collection $products, ?User $customer = null): string
    {
        if ($products->isEmpty()) {
            return '- Không tìm thấy sản phẩm phù hợp rõ ràng trong dữ liệu hiện có.';
        }

        $tierId = $this->resolveEffectiveTierId($customer);

        return $products->take(6)->map(function (Product $product) use ($tierId) {
            $pricing = $this->resolveUnitPriceWithMinQty($product->prices, $tierId, 1);
            $price = (float) ($pricing['unit_price'] ?? 0);
            $colors = $product->colors->pluck('color_name')->filter()->take(5)->implode(', ');
            $lines = [
                "- [#{$product->id}] {$product->name}",
                '  Danh mục: ' . ($product->category?->name ?? 'Khác'),
                '  Giá tham khảo: ' . ($price > 0 ? number_format($price, 0, ',', '.') . ' đ' : 'chưa có'),
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

    private function buildFallbackReply(Collection $products, string $question, ?User $customer = null): string
    {
        if ($products->isEmpty()) {
            return "Mình chưa có đủ dữ liệu để trả lời thật sát cho nhu cầu \"{$question}\" ngay lúc này. Bạn có thể nói rõ hơn để mình kiểm tra thêm, hoặc nếu cần hỗ trợ chắc chắn hơn thì mình gợi ý bạn nhắn trực tiếp với quản lý/hỗ trợ của hệ thống nhé.";
        }

        $lines = [
            "Mình gợi ý một vài sản phẩm gần với nhu cầu \"{$question}\":",
        ];

        $tierId = $this->resolveEffectiveTierId($customer);

        foreach ($products->take(3) as $product) {
            $pricing = $this->resolveUnitPriceWithMinQty($product->prices, $tierId, 1);
            $price = (float) ($pricing['unit_price'] ?? 0);
            $line = "- {$product->name}";
            if ($product->category?->name) {
                $line .= " ({$product->category->name})";
            }
            if ($price > 0) {
                $line .= ' - khoảng ' . number_format($price, 0, ',', '.') . ' đ';
            }
            $lines[] = $line;
        }

        $lines[] = 'Nếu bạn muốn, mình có thể gợi ý tiếp theo mục đích dùng, màu sắc hoặc mức giá. Trường hợp bạn cần xác nhận thêm thông tin chi tiết ngoài dữ liệu hiện có, mình gợi ý bạn nhắn quản lý/hỗ trợ của hệ thống nhé.';

        return implode("\n", $lines);
    }
}

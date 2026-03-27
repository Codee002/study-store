<?php

namespace App\Services;

use App\Events\NotificationPushed;
use App\Models\Evaluate;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;

class NotificationService
{
    /**
     * Gửi thông báo đơn hàng cho khách và toàn bộ admin.
     */
    public function notifyOrderChange(Order $order, string $content): void
    {
        if (! $order->id) {
            return;
        }

        $payload = [
            'type'    => 'order',
            'content' => $content,
            'url_id'  => $order->id,
            'status'  => 'unread',
        ];

        if ($order->user_id) {
            $this->createAndBroadcast(array_merge($payload, ['user_id' => $order->user_id]));
        }

        $adminIds = User::query()
            ->where('role', 'admin')
            ->pluck('id')
            ->all();

        foreach ($adminIds as $adminId) {
            if ((int) $adminId === (int) $order->user_id) {
                continue;
            }
            $this->createAndBroadcast(array_merge($payload, ['user_id' => (int) $adminId]));
        }
    }

    /**
     * Gửi thông báo phiếu nhập cho toàn bộ admin.
     */
    public function notifyReceiptChange(int $receiptId, string $content): void
    {
        if ($receiptId <= 0) {
            return;
        }

        $payload = [
            'type'    => 'receipt',
            'content' => $content,
            'url_id'  => $receiptId,
            'status'  => 'unread',
        ];

        $adminIds = User::query()
            ->where('role', 'admin')
            ->pluck('id')
            ->all();

        foreach ($adminIds as $adminId) {
            $this->createAndBroadcast(array_merge($payload, ['user_id' => (int) $adminId]));
        }
    }

    /**
     * Sinh nội dung thông báo khi trạng thái đổi.
     */
    public function notifyEvaluateSubmitted(Evaluate $evaluate): void
    {
        $evaluate->loadMissing([
            'order.user.profile',
            'product:id,name',
        ]);

        $orderId = (int) ($evaluate->order_id ?? 0);
        if ($orderId <= 0) {
            return;
        }

        $customerName = trim((string) ($evaluate->order?->user?->profile?->name
            ?: $evaluate->order?->user?->username
            ?: 'Khách hàng'));
        $productName = trim((string) ($evaluate->product?->name ?: 'sản phẩm'));
        $rating = max(1, min(5, (int) round((float) ($evaluate->rating ?? 0))));

        $payload = [
            'type'    => 'evaluate',
            'content' => "{$customerName} đã đánh giá {$rating} sao cho {$productName} trong đơn hàng #{$orderId}.",
            'url_id'  => $orderId,
            'status'  => 'unread',
        ];

        $adminIds = User::query()
            ->where('role', 'admin')
            ->pluck('id')
            ->all();

        foreach ($adminIds as $adminId) {
            $this->createAndBroadcast(array_merge($payload, ['user_id' => (int) $adminId]));
        }
    }

    public function notifyEvaluateReply(Evaluate $evaluate): void
    {
        $evaluate->loadMissing([
            'order.user',
            'product:id,name',
        ]);

        $orderId = (int) ($evaluate->order_id ?? 0);
        $customerId = (int) ($evaluate->order?->user_id ?? 0);
        if ($orderId <= 0 || $customerId <= 0) {
            return;
        }

        $productName = trim((string) ($evaluate->product?->name ?: 'san pham'));

        $this->createAndBroadcast([
            'user_id' => $customerId,
            'type'    => 'evaluate-reply',
            'content' => "Shop đã phản hồi đánh giá của bạn cho {$productName} trong đơn hàng #{$orderId}.",
            'url_id'  => $orderId,
            'status'  => 'unread',
        ]);
    }

    public function buildOrderStatusMessage(Order $order, string $oldStatus, string $newStatus): string
    {
        $oldLabel = $this->statusLabel($oldStatus);
        $newLabel = $this->statusLabel($newStatus);

        if ($oldStatus === '' || $oldStatus === null) {
            return "Đơn hàng #{$order->id} đã được tạo (trạng thái {$newLabel}).";
        }

        if ($oldStatus === $newStatus) {
            return "Đơn hàng #{$order->id} cập nhật trạng thái: {$newLabel}.";
        }

        return "Đơn hàng #{$order->id} đổi trạng thái từ {$oldLabel} sang {$newLabel}.";
    }

    private function statusLabel(?string $status): string
    {
        return match ((string) $status) {
            'pending'   => 'Chờ xác nhận',
            'shipping'  => 'Đang giao',
            'completed' => 'Hoàn tất',
            'cancelled' => 'Đã hủy',
            'rejected'  => 'Từ chối',
            default     => $status ?: 'khác',
        };
    }

    private function createAndBroadcast(array $data): void
    {
        $notification = Notification::create($data)->fresh();
        NotificationPushed::dispatch($notification);
    }
}

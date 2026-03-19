<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\NotificationService;

class OrderObserver
{
    public function created(Order $order): void
    {
        $service = app(NotificationService::class);

        $service->notifyOrderChange(
            $order,
            $service->buildOrderStatusMessage($order, '', (string) $order->status)
        );
    }

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $oldStatus = (string) $order->getOriginal('status');
        $newStatus = (string) $order->status;

        $service = app(NotificationService::class);

        $service->notifyOrderChange(
            $order,
            $service->buildOrderStatusMessage($order, $oldStatus, $newStatus)
        );
    }
}

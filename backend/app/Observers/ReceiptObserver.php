<?php

namespace App\Observers;

use App\Models\Receipt;
use App\Services\NotificationService;

class ReceiptObserver
{
    public function created(Receipt $receipt): void
    {
        $content = "Phiếu nhập #{$receipt->id} được tạo (trạng thái {$receipt->status}).";
        app(NotificationService::class)->notifyReceiptChange($receipt->id, $content);
    }

    public function updated(Receipt $receipt): void
    {
        if (! $receipt->wasChanged('status')) {
            return;
        }

        $old = (string) $receipt->getOriginal('status');
        $new = (string) $receipt->status;
        $content = "Phiếu nhập #{$receipt->id} đổi trạng thái từ {$old} sang {$new}.";

        app(NotificationService::class)->notifyReceiptChange($receipt->id, $content);
    }
}

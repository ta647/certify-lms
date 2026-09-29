<?php

declare(strict_types=1);

namespace App\UseCases\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Stripe Webhookの`checkout.session.expired`(未決済のまま放置・キャンセル)から呼ばれる。
 * pendingのままの場合のみfailedへ更新する(既にsucceededなら何もしない)。
 */
final class ExpireCheckoutAction
{
    public function __invoke(string $paymentId): void
    {
        DB::transaction(function () use ($paymentId) {
            $payment = Payment::query()->whereKey($paymentId)->lockForUpdate()->first();

            if ($payment === null || $payment->status !== PaymentStatus::Pending) {
                return;
            }

            $payment->update(['status' => PaymentStatus::Failed->value]);
        });
    }
}

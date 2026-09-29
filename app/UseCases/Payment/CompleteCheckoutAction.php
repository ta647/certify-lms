<?php

declare(strict_types=1);

namespace App\UseCases\Payment;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Stripe Webhookの`checkout.session.completed`から呼ばれる、決済完了処理のユースケース。
 *
 * `client_reference_id`(Payment.id)で対象行をlockForUpdateし、既にsucceededなら何もしない
 * (Webhookの重複配信で残数が二重加算されるのを防ぐ冪等性)。pendingの場合のみ、
 * succeededへの更新と面談回数の付与(MeetingQuotaTransaction)を同一トランザクションで行う。
 */
final class CompleteCheckoutAction
{
    public function __invoke(string $paymentId, ?string $paymentIntentId): void
    {
        DB::transaction(function () use ($paymentId, $paymentIntentId) {
            $payment = Payment::query()->whereKey($paymentId)->lockForUpdate()->first();

            if ($payment === null || $payment->status !== PaymentStatus::Pending) {
                return;
            }

            $payment->update([
                'status' => PaymentStatus::Succeeded->value,
                'stripe_payment_intent_id' => $paymentIntentId,
                'paid_at' => now(),
            ]);

            MeetingQuotaTransaction::create([
                'user_id' => $payment->user_id,
                'type' => MeetingQuotaTransactionType::Purchased->value,
                'amount' => $payment->quantity,
                'related_payment_id' => $payment->id,
                'occurred_at' => now(),
            ]);
        });
    }
}

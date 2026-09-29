<?php

declare(strict_types=1);

namespace App\UseCases\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Stripe Webhookの`charge.refunded`から呼ばれる。
 *
 * 返金は管理者がStripeダッシュボードから手動操作する運用(本チケットのスコープ外)で、
 * このアクションは「返金が起きた事実」を監査目的でPaymentに反映するのみ。
 * 面談回数の取消(MeetingQuotaTransaction)は行わない(スコープ外)。
 * succeeded以外(pending/failed/既にrefunded)の場合は何もしない(冪等)。
 */
final class RefundPaymentAction
{
    public function __invoke(string $paymentIntentId): void
    {
        DB::transaction(function () use ($paymentIntentId) {
            $payment = Payment::query()
                ->where('stripe_payment_intent_id', $paymentIntentId)
                ->lockForUpdate()
                ->first();

            if ($payment === null || $payment->status !== PaymentStatus::Succeeded) {
                return;
            }

            $payment->update(['status' => PaymentStatus::Refunded->value]);
        });
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\UseCases\Payment\RefundPaymentAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundPaymentActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_succeeded_payment_as_refunded(): void
    {
        $payment = Payment::factory()->succeeded()->create();

        app(RefundPaymentAction::class)($payment->stripe_payment_intent_id);

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
    }

    public function test_does_nothing_for_pending_payment(): void
    {
        $payment = Payment::factory()->create(['status' => PaymentStatus::Pending->value]);

        app(RefundPaymentAction::class)('pi_unrelated');

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_is_idempotent_for_already_refunded_payment(): void
    {
        $payment = Payment::factory()->succeeded()->create();
        app(RefundPaymentAction::class)($payment->stripe_payment_intent_id);

        app(RefundPaymentAction::class)($payment->stripe_payment_intent_id);

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
    }
}

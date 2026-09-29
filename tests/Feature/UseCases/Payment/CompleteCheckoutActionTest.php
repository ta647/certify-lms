<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Payment;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Services\MeetingQuotaService;
use App\UseCases\Payment\CompleteCheckoutAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteCheckoutActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_payment_succeeded_and_credits_quota(): void
    {
        $payment = Payment::factory()->create(['status' => PaymentStatus::Pending->value, 'quantity' => 5]);

        app(CompleteCheckoutAction::class)($payment->id, 'pi_test_abc');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Succeeded, $payment->status);
        $this->assertSame('pi_test_abc', $payment->stripe_payment_intent_id);
        $this->assertNotNull($payment->paid_at);

        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $payment->user_id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => 5,
            'related_payment_id' => $payment->id,
        ]);

        $remaining = app(MeetingQuotaService::class)->remaining($payment->user);
        $this->assertSame(5, $remaining);
    }

    public function test_does_not_double_credit_when_already_succeeded(): void
    {
        $payment = Payment::factory()->succeeded()->create(['quantity' => 5]);
        MeetingQuotaTransaction::create([
            'user_id' => $payment->user_id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => 5,
            'related_payment_id' => $payment->id,
            'occurred_at' => now(),
        ]);

        app(CompleteCheckoutAction::class)($payment->id, 'pi_test_retry');

        $this->assertSame(1, MeetingQuotaTransaction::where('related_payment_id', $payment->id)->count());
        $this->assertNotSame('pi_test_retry', $payment->fresh()->stripe_payment_intent_id);
    }

    public function test_does_nothing_for_unknown_payment_id(): void
    {
        app(CompleteCheckoutAction::class)('nonexistent-id', 'pi_test');

        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }
}

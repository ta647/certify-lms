<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\UseCases\Payment\ExpireCheckoutAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireCheckoutActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_pending_payment_as_failed(): void
    {
        $payment = Payment::factory()->create(['status' => PaymentStatus::Pending->value]);

        app(ExpireCheckoutAction::class)($payment->id);

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
    }

    public function test_does_not_touch_already_succeeded_payment(): void
    {
        $payment = Payment::factory()->succeeded()->create();

        app(ExpireCheckoutAction::class)($payment->id);

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
    }
}

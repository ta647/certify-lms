<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Payment;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Event;
use Tests\TestCase;

class StripeWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private function fakeEvent(string $type, array $object): Event
    {
        return Event::constructFrom(['id' => 'evt_test', 'type' => $type, 'data' => ['object' => $object]]);
    }

    private function mockSignatureVerification(Event $event): void
    {
        $mock = Mockery::mock(StripeCheckoutService::class);
        $mock->shouldReceive('verifyWebhookSignature')->once()->andReturn($event);
        $this->app->instance(StripeCheckoutService::class, $mock);
    }

    public function test_invalid_signature_returns_400(): void
    {
        $mock = Mockery::mock(StripeCheckoutService::class);
        $mock->shouldReceive('verifyWebhookSignature')->once()->andThrow(
            \Stripe\Exception\SignatureVerificationException::factory('bad signature'),
        );
        $this->app->instance(StripeCheckoutService::class, $mock);

        $this->postJson(route('webhooks.stripe'), [])->assertStatus(400);
    }

    public function test_checkout_session_completed_credits_quota(): void
    {
        $payment = Payment::factory()->create(['status' => PaymentStatus::Pending->value, 'quantity' => 5]);
        $this->mockSignatureVerification($this->fakeEvent('checkout.session.completed', [
            'client_reference_id' => $payment->id,
            'payment_intent' => 'pi_test_abc',
        ]));

        $response = $this->postJson(route('webhooks.stripe'), []);

        $response->assertOk();
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'related_payment_id' => $payment->id,
            'type' => MeetingQuotaTransactionType::Purchased->value,
            'amount' => 5,
        ]);
    }

    public function test_duplicate_checkout_session_completed_does_not_double_credit(): void
    {
        $payment = Payment::factory()->create(['status' => PaymentStatus::Pending->value, 'quantity' => 5]);
        $event = $this->fakeEvent('checkout.session.completed', [
            'client_reference_id' => $payment->id,
            'payment_intent' => 'pi_test_abc',
        ]);

        $mock = Mockery::mock(StripeCheckoutService::class);
        $mock->shouldReceive('verifyWebhookSignature')->twice()->andReturn($event);
        $this->app->instance(StripeCheckoutService::class, $mock);

        $this->postJson(route('webhooks.stripe'), [])->assertOk();
        $this->postJson(route('webhooks.stripe'), [])->assertOk();

        $this->assertSame(1, \App\Models\MeetingQuotaTransaction::where('related_payment_id', $payment->id)->count());
    }

    public function test_checkout_session_expired_marks_payment_failed(): void
    {
        $payment = Payment::factory()->create(['status' => PaymentStatus::Pending->value]);
        $this->mockSignatureVerification($this->fakeEvent('checkout.session.expired', [
            'client_reference_id' => $payment->id,
        ]));

        $this->postJson(route('webhooks.stripe'), [])->assertOk();

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
    }

    public function test_charge_refunded_marks_payment_refunded(): void
    {
        $payment = Payment::factory()->succeeded()->create();
        $this->mockSignatureVerification($this->fakeEvent('charge.refunded', [
            'payment_intent' => $payment->stripe_payment_intent_id,
        ]));

        $this->postJson(route('webhooks.stripe'), [])->assertOk();

        $this->assertSame(PaymentStatus::Refunded, $payment->fresh()->status);
    }

    public function test_unrecognized_event_type_is_ignored_with_200(): void
    {
        $this->mockSignatureVerification($this->fakeEvent('customer.created', ['id' => 'cus_test']));

        $this->postJson(route('webhooks.stripe'), [])->assertOk();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Payment;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Services\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Tests\Support\StripeSignatureTestHelpers;
use Tests\TestCase;

#[Group('external-api')]
class StripeWebhookControllerTest extends TestCase
{
    use RefreshDatabase, StripeSignatureTestHelpers;

    private const WEBHOOK_SECRET = 'whsec_test_secret';

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
            SignatureVerificationException::factory('bad signature'),
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

        $this->assertSame(1, MeetingQuotaTransaction::where('related_payment_id', $payment->id)->count());
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

    /**
     * ここから下は StripeCheckoutService をモックせず、実際の署名検証ロジックを通す
     * エンドツーエンドのテスト(正規署名・不正署名・署名欠落)。
     */
    public function test_real_signature_verification_accepts_genuinely_signed_payload(): void
    {
        config(['services.stripe.webhook_secret' => self::WEBHOOK_SECRET]);
        $payment = Payment::factory()->create(['status' => PaymentStatus::Pending->value, 'quantity' => 3]);

        $payload = json_encode([
            'id' => 'evt_real_test',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['client_reference_id' => $payment->id, 'payment_intent' => 'pi_real_test']],
        ]);
        $signature = $this->generateStripeSignatureHeader($payload, self::WEBHOOK_SECRET);

        $response = $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'HTTP_Stripe-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertOk();
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
    }

    public function test_real_signature_verification_rejects_tampered_payload(): void
    {
        config(['services.stripe.webhook_secret' => self::WEBHOOK_SECRET]);
        $payment = Payment::factory()->create(['status' => PaymentStatus::Pending->value, 'quantity' => 3]);

        $signedPayload = json_encode([
            'id' => 'evt_real_test',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['client_reference_id' => $payment->id, 'payment_intent' => 'pi_real_test']],
        ]);
        $signature = $this->generateStripeSignatureHeader($signedPayload, self::WEBHOOK_SECRET);
        // 署名計算後にペイロードを書き換える(なりすまし・改ざんを想定)
        $tamperedPayload = json_encode([
            'id' => 'evt_real_test',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['client_reference_id' => $payment->id, 'payment_intent' => 'pi_hijacked']],
        ]);

        $response = $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'HTTP_Stripe-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $tamperedPayload);

        $response->assertStatus(400);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status, '署名不正時は決済を完了させないはず');
    }

    public function test_real_signature_verification_rejects_missing_signature_header(): void
    {
        config(['services.stripe.webhook_secret' => self::WEBHOOK_SECRET]);
        $payload = json_encode(['id' => 'evt_real_test', 'type' => 'checkout.session.completed']);

        $response = $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(400);
    }
}

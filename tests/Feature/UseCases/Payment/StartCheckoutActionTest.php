<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Payment;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use App\Services\StripeCheckoutService;
use App\UseCases\Payment\StartCheckoutAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Checkout\Session;
use Tests\TestCase;

class StartCheckoutActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_pending_payment_and_returns_checkout_url(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $pack = MeetingPack::factory()->published()->create(['meeting_count' => 5, 'price' => 5000]);

        $session = Session::constructFrom(['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/cs_test_123']);

        $mock = Mockery::mock(StripeCheckoutService::class);
        $mock->shouldReceive('createCheckoutSession')->once()->andReturn($session);
        $this->app->instance(StripeCheckoutService::class, $mock);

        $url = app(StartCheckoutAction::class)($student, $pack, 'https://app.test/success', 'https://app.test/cancel');

        $this->assertSame('https://checkout.stripe.com/cs_test_123', $url);

        $payment = Payment::where('user_id', $student->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(5, $payment->quantity);
        $this->assertSame(5000, $payment->amount);
        $this->assertSame('cs_test_123', $payment->stripe_checkout_session_id);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Payment;

use App\Enums\MeetingPackStatus;
use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use App\Services\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Checkout\Session;
use Tests\TestCase;

class MeetingQuotaCheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_select_shows_only_published_packs(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $published = MeetingPack::factory()->published()->create(['name' => '公開中パック']);
        MeetingPack::factory()->create(['status' => MeetingPackStatus::Draft->value, 'name' => '下書きパック']);

        $response = $this->actingAs($student)->get(route('meeting-quota.checkout.select'));

        $response->assertOk();
        $response->assertSee('公開中パック');
        $response->assertDontSee('下書きパック');
    }

    public function test_create_redirects_to_stripe_checkout_url(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $pack = MeetingPack::factory()->published()->create();

        $session = Session::constructFrom(['id' => 'cs_test_xyz', 'url' => 'https://checkout.stripe.com/cs_test_xyz']);
        $mock = Mockery::mock(StripeCheckoutService::class);
        $mock->shouldReceive('createCheckoutSession')->once()->andReturn($session);
        $this->app->instance(StripeCheckoutService::class, $mock);

        $response = $this->actingAs($student)->post(route('meeting-quota.checkout.create'), [
            'meeting_pack_id' => $pack->id,
        ]);

        $response->assertRedirect('https://checkout.stripe.com/cs_test_xyz');
        $this->assertDatabaseHas('payments', [
            'user_id' => $student->id,
            'meeting_pack_id' => $pack->id,
            'status' => PaymentStatus::Pending->value,
        ]);
    }

    public function test_create_rejects_non_published_pack(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $pack = MeetingPack::factory()->create(['status' => MeetingPackStatus::Draft->value]);

        $response = $this->actingAs($student)->post(route('meeting-quota.checkout.create'), [
            'meeting_pack_id' => $pack->id,
        ]);

        $response->assertSessionHasErrors('meeting_pack_id');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_create_rejects_archived_pack(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $pack = MeetingPack::factory()->create(['status' => MeetingPackStatus::Archived->value]);

        $this->actingAs($student)->post(route('meeting-quota.checkout.create'), [
            'meeting_pack_id' => $pack->id,
        ])->assertSessionHasErrors('meeting_pack_id');
    }

    public function test_success_shows_payment_for_owner(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $payment = Payment::factory()->forUser($student)->succeeded()->create([
            'stripe_checkout_session_id' => 'cs_test_owner',
        ]);

        $response = $this->actingAs($student)->get(route('meeting-quota.success', ['session_id' => 'cs_test_owner']));

        $response->assertOk();
        $response->assertViewHas('payment', fn ($p) => $p->id === $payment->id);
    }

    public function test_success_does_not_leak_other_users_payment(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        Payment::factory()->forUser($owner)->succeeded()->create(['stripe_checkout_session_id' => 'cs_test_other']);

        $response = $this->actingAs($other)->get(route('meeting-quota.success', ['session_id' => 'cs_test_other']));

        $response->assertOk();
        $response->assertViewHas('payment', null);
    }

    public function test_success_without_session_id_shows_null_payment(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->get(route('meeting-quota.success'));

        $response->assertOk();
        $response->assertViewHas('payment', null);
    }

    public function test_graduated_student_cannot_access_checkout(): void
    {
        $student = User::factory()->student()->graduated()->create();

        $this->actingAs($student)->get(route('meeting-quota.checkout.select'))->assertForbidden();
    }

    public function test_coach_cannot_access_checkout(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();

        $this->actingAs($coach)->get(route('meeting-quota.checkout.select'))->assertForbidden();
    }
}

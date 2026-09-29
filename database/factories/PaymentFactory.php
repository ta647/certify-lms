<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'meeting_pack_id' => MeetingPack::factory(),
            'quantity' => 5,
            'amount' => 5000,
            'status' => PaymentStatus::Pending->value,
            'stripe_checkout_session_id' => 'cs_test_'.fake()->unique()->uuid(),
            'stripe_payment_intent_id' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    public function forPack(MeetingPack $pack): static
    {
        return $this->state(fn () => [
            'meeting_pack_id' => $pack->id,
            'quantity' => $pack->meeting_count,
            'amount' => $pack->price,
        ]);
    }

    public function succeeded(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Succeeded->value,
            'stripe_payment_intent_id' => 'pi_test_'.fake()->unique()->uuid(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Failed->value]);
    }
}

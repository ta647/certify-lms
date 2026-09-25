<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GoogleCalendarCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleCalendarCredential>
 */
class GoogleCalendarCredentialFactory extends Factory
{
    protected $model = GoogleCalendarCredential::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->coach(),
            'access_token' => 'test-access-token-'.fake()->uuid(),
            'refresh_token' => 'test-refresh-token-'.fake()->uuid(),
            'token_expires_at' => now()->addHour(),
            'calendar_id' => 'primary',
            'connected_at' => now(),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }
}

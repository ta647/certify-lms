<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnrollmentGoal>
 */
class EnrollmentGoalFactory extends Factory
{
    protected $model = EnrollmentGoal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->realText(150),
            'target_date' => fake()->optional()->dateTimeBetween('now', '+60 days'),
            'achieved_at' => null,
        ];
    }

    public function achieved(): static
    {
        return $this->state(fn () => ['achieved_at' => now()]);
    }

    public function withTargetDate(?\DateTimeInterface $date): static
    {
        return $this->state(fn () => ['target_date' => $date]);
    }
}

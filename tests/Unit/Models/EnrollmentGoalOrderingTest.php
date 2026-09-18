<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `Enrollment::goals()` の並び順(未達成優先 → 期日近い順 → 期日なしは後ろ → 作成日新しい順)を検証する。
 */
class EnrollmentGoalOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_goals_are_ordered_per_business_rule(): void
    {
        $enrollment = Enrollment::factory()->create();

        $achievedNear = EnrollmentGoal::factory()->achieved()->withTargetDate(now()->addDays(5))->for($enrollment)->create();
        $unachievedFar = EnrollmentGoal::factory()->withTargetDate(now()->addDays(20))->for($enrollment)->create();
        $unachievedNear = EnrollmentGoal::factory()->withTargetDate(now()->addDays(3))->for($enrollment)->create();
        $unachievedNoDate = EnrollmentGoal::factory()->withTargetDate(null)->for($enrollment)->create();

        $orderedIds = $enrollment->goals->pluck('id')->all();

        $this->assertSame([
            $unachievedNear->id,
            $unachievedFar->id,
            $unachievedNoDate->id,
            $achievedNear->id,
        ], $orderedIds);
    }

    public function test_goals_are_deleted_when_enrollment_is_deleted(): void
    {
        $enrollment = Enrollment::factory()->create();
        $goal = EnrollmentGoal::factory()->for($enrollment)->create();

        $enrollment->delete();

        $this->assertDatabaseMissing('enrollment_goals', ['id' => $goal->id]);
    }
}

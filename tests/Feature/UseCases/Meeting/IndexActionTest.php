<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\IndexAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_own_meetings(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $own = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        $other = Meeting::factory()->reserved()->forCoach($coach)->forStudent($otherStudent)->create([
            'scheduled_at' => now()->addDays(4)->startOfHour(),
        ]);

        $meetings = app(IndexAction::class)($student);

        $this->assertTrue($meetings->contains('id', $own->id));
        $this->assertFalse($meetings->contains('id', $other->id));
    }

    public function test_past_filter_excludes_upcoming_meetings(): void
    {
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $upcoming = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        $past = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subDays(3)->startOfHour(),
        ]);

        $meetings = app(IndexAction::class)($student, 'past');

        $this->assertTrue($meetings->contains('id', $past->id));
        $this->assertFalse($meetings->contains('id', $upcoming->id));
    }
}

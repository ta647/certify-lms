<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_students_target_notifies_only_in_progress_students(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $activeStudent = User::factory()->student()->inProgress()->create();
        $graduatedStudent = User::factory()->student()->graduated()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'メンテナンスのお知らせ',
            'body' => '本日22時よりメンテナンスを行います。',
            'target_type' => 'all_students',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('announcements', ['title' => 'メンテナンスのお知らせ', 'dispatched_count' => 1]);
        Notification::assertSentTo($activeStudent, AnnouncementNotification::class);
        Notification::assertNotSentTo($graduatedStudent, AnnouncementNotification::class);
        Notification::assertNotSentTo($coach, AnnouncementNotification::class);
    }

    public function test_certification_target_requires_learning_enrollment(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        $learningStudent = User::factory()->student()->inProgress()->create();
        Enrollment::factory()->for($learningStudent, 'user')->for($certification)->create(['status' => EnrollmentStatus::Learning->value]);

        $passedStudent = User::factory()->student()->inProgress()->create();
        Enrollment::factory()->for($passedStudent, 'user')->for($certification)->create(['status' => EnrollmentStatus::Passed->value]);

        $unrelatedStudent = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => '資格Xの教材更新',
            'body' => '教材を更新しました。',
            'target_type' => 'certification',
            'target_certification_id' => $certification->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('announcements', ['dispatched_count' => 1]);
        Notification::assertSentTo($learningStudent, AnnouncementNotification::class);
        Notification::assertNotSentTo($passedStudent, AnnouncementNotification::class);
        Notification::assertNotSentTo($unrelatedStudent, AnnouncementNotification::class);
    }

    public function test_user_target_to_ineligible_user_dispatches_zero_but_still_succeeds(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => '個別連絡',
            'body' => '本文',
            'target_type' => 'user',
            'target_user_id' => $coach->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('announcements', ['title' => '個別連絡', 'dispatched_count' => 0]);
        $this->assertNotNull(\App\Models\Announcement::where('title', '個別連絡')->first()->dispatched_at);
        Notification::assertNotSentTo($coach, AnnouncementNotification::class);
    }

    public function test_user_target_to_eligible_student_notifies(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => '個別連絡2',
            'body' => '本文',
            'target_type' => 'user',
            'target_user_id' => $student->id,
        ]);

        $this->assertDatabaseHas('announcements', ['title' => '個別連絡2', 'dispatched_count' => 1]);
        Notification::assertSentTo($student, AnnouncementNotification::class);
    }

    public function test_certification_id_required_when_target_type_is_certification(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => 'certification',
        ])->assertSessionHasErrors('target_certification_id');
    }

    public function test_non_admin_forbidden(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)->post(route('admin.announcements.store'), [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => 'all_students',
        ])->assertForbidden();
    }
}

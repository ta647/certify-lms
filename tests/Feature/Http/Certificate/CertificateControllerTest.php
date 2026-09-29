<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Certificate;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CertificateControllerTest extends TestCase
{
    use RefreshDatabase;

    private function fakeStoredCertificate(array $attributes = []): Certificate
    {
        Storage::fake('private');
        $certificate = Certificate::factory()->create($attributes);
        Storage::disk('private')->put($certificate->pdf_path, '%PDF-1.4 fake content');

        return $certificate;
    }

    public function test_owner_student_can_download(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certificate = $this->fakeStoredCertificate(['user_id' => $student->id]);

        $response = $this->actingAs($student)->get(route('certificates.download', $certificate));

        $response->assertOk();
    }

    public function test_graduated_student_can_still_download_own_certificate(): void
    {
        $student = User::factory()->student()->graduated()->create();
        $certificate = $this->fakeStoredCertificate(['user_id' => $student->id]);

        $this->actingAs($student)->get(route('certificates.download', $certificate))->assertOk();
    }

    public function test_other_student_cannot_download(): void
    {
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->inProgress()->create();
        $certificate = $this->fakeStoredCertificate(['user_id' => $owner->id]);

        $this->actingAs($other)->get(route('certificates.download', $certificate))->assertForbidden();
    }

    public function test_assigned_coach_can_download(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();
        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $certificate = $this->fakeStoredCertificate(['certification_id' => $certification->id]);

        $this->actingAs($coach)->get(route('certificates.download', $certificate))->assertOk();
    }

    public function test_unassigned_coach_cannot_download(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $certificate = $this->fakeStoredCertificate();

        $this->actingAs($coach)->get(route('certificates.download', $certificate))->assertForbidden();
    }

    public function test_returns_404_when_file_missing_from_storage(): void
    {
        Storage::fake('private');
        $student = User::factory()->student()->inProgress()->create();
        $certificate = Certificate::factory()->create(['user_id' => $student->id]);
        // ファイルはStorageに書き込まない(発行時の失敗などで欠落しているケースを再現)

        $this->actingAs($student)->get(route('certificates.download', $certificate))->assertNotFound();
    }
}

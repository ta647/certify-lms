<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certificate;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\User;
use App\Policies\CertificatePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CertificatePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_any_certificate(): void
    {
        $admin = User::factory()->admin()->create();
        $certificate = Certificate::factory()->create();

        $this->assertTrue((new CertificatePolicy)->download($admin, $certificate));
    }

    public function test_student_can_download_own_certificate_only(): void
    {
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $certificate = Certificate::factory()->for($owner, 'user')->create();

        $policy = new CertificatePolicy;
        $this->assertTrue($policy->download($owner, $certificate));
        $this->assertFalse($policy->download($other, $certificate));
    }

    public function test_coach_can_download_only_assigned_certification(): void
    {
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $assignedCert = Certification::factory()->published()->create();
        $otherCert = Certification::factory()->published()->create();
        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $assignedCert->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $assignedCertificate = Certificate::factory()->for($assignedCert, 'certification')->create();
        $otherCertificate = Certificate::factory()->for($otherCert, 'certification')->create();

        $policy = new CertificatePolicy;
        $this->assertTrue($policy->download($coach, $assignedCertificate));
        $this->assertFalse($policy->download($coach, $otherCertificate));
    }
}

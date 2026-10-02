<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentStatusLog;
use App\Models\User;
use App\Services\EnrollmentStatusChangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * T-A-06: EnrollmentStatusChangeService::recordStatusChange() が、監査ログの記録に加えて
 * 管理者ダッシュボード集計キャッシュ(KPI / 資格別修了率)の両方を無効化することを検証する。
 * 全 Enrollment 状態遷移(新規登録 / 合格 / 不合格 等)がこの唯一の choke point を通るため、
 * ここで無効化すれば呼び出し元(FailAction 等)を個別に手当てする必要がない。
 */
class EnrollmentStatusChangeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_status_log(): void
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->learning()->create();
        $admin = User::factory()->admin()->create();

        $log = app(EnrollmentStatusChangeService::class)->recordStatusChange(
            $enrollment,
            EnrollmentStatus::Learning,
            EnrollmentStatus::Failed,
            $admin,
            'テスト理由',
        );

        $this->assertInstanceOf(EnrollmentStatusLog::class, $log);
        $this->assertDatabaseHas('enrollment_status_logs', [
            'enrollment_id' => $enrollment->id,
            'from_status' => EnrollmentStatus::Learning->value,
            'to_status' => EnrollmentStatus::Failed->value,
            'changed_by_user_id' => $admin->id,
            'changed_reason' => 'テスト理由',
        ]);
    }

    public function test_forgets_both_admin_dashboard_cache_keys(): void
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->learning()->create();

        Cache::put(config('dashboard.admin_kpi_cache_key'), ['stale' => true], 3600);
        Cache::put(config('dashboard.admin_completion_rate_cache_key'), ['stale' => true], 3600);

        app(EnrollmentStatusChangeService::class)->recordStatusChange(
            $enrollment,
            EnrollmentStatus::Learning,
            EnrollmentStatus::Passed,
            null,
        );

        $this->assertFalse(Cache::has(config('dashboard.admin_kpi_cache_key')), 'KPIキャッシュが無効化されるはず');
        $this->assertFalse(Cache::has(config('dashboard.admin_completion_rate_cache_key')), '修了率キャッシュが無効化されるはず');
    }

    public function test_forgets_cache_on_initial_enrollment_with_null_from_status(): void
    {
        // 新規受講登録(fromStatus=null)でも無効化されることを確認する
        $certification = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($certification)->learning()->create();

        Cache::put(config('dashboard.admin_kpi_cache_key'), ['stale' => true], 3600);

        app(EnrollmentStatusChangeService::class)->recordStatusChange(
            $enrollment,
            null,
            EnrollmentStatus::Learning,
            $enrollment->user,
            '新規登録',
        );

        $this->assertFalse(Cache::has(config('dashboard.admin_kpi_cache_key')));
    }
}

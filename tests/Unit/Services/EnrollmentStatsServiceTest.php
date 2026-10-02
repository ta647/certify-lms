<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Services\EnrollmentStatsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * T-A-06: EnrollmentStatsService の重い集計(adminKpi / completionRateByCertification)が
 * 設定キー・設定TTLでキャッシュされることを検証する。無効化のタイミング(状態遷移時)は
 * EnrollmentStatusChangeServiceTest / AdminDashboardCacheTest で別途検証済み。
 */
class EnrollmentStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_admin_kpi_is_cached_under_configured_key(): void
    {
        $cert = Certification::factory()->published()->create();
        Enrollment::factory()->for($cert)->learning()->create();

        app(EnrollmentStatsService::class)->adminKpi();

        $this->assertTrue(Cache::has(config('dashboard.admin_kpi_cache_key')));
    }

    public function test_completion_rate_is_cached_under_configured_key(): void
    {
        $cert = Certification::factory()->published()->create();
        Enrollment::factory()->for($cert)->learning()->create();

        app(EnrollmentStatsService::class)->completionRateByCertification();

        $this->assertTrue(Cache::has(config('dashboard.admin_completion_rate_cache_key')));
    }

    public function test_admin_kpi_recomputes_after_configured_ttl_expires(): void
    {
        config(['dashboard.admin_cache_ttl' => 300]);
        $cert = Certification::factory()->published()->create();
        Enrollment::factory()->for($cert)->learning()->count(2)->create();

        $first = app(EnrollmentStatsService::class)->adminKpi();
        $this->assertSame(2, $first['learning_count']);

        // TTL内: 直接INSERTしてもキャッシュ値のまま
        Enrollment::factory()->for($cert)->learning()->create();
        $withinTtl = app(EnrollmentStatsService::class)->adminKpi();
        $this->assertSame(2, $withinTtl['learning_count'], 'TTL内はキャッシュされた値が返るはず');

        // TTLを過ぎたら再計算される
        Carbon::setTestNow(now()->addSeconds(301));
        $afterTtl = app(EnrollmentStatsService::class)->adminKpi();
        $this->assertSame(3, $afterTtl['learning_count'], 'TTL経過後は再計算され最新値が返るはず');
    }

    public function test_shorter_configured_ttl_expires_cache_sooner(): void
    {
        // TTLが設定値で調整できることの確認: 10秒に設定し、11秒後には再計算されるはず
        config(['dashboard.admin_cache_ttl' => 10]);
        $cert = Certification::factory()->published()->create();
        Enrollment::factory()->for($cert)->learning()->create();

        app(EnrollmentStatsService::class)->adminKpi();
        Enrollment::factory()->for($cert)->learning()->create();

        Carbon::setTestNow(now()->addSeconds(11));
        $after = app(EnrollmentStatsService::class)->adminKpi();

        $this->assertSame(2, $after['learning_count'], '短いTTLに設定した場合はより早く再計算されるはず');
    }
}

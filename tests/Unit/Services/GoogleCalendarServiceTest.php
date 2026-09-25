<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * GoogleCalendarService::busyKeysFromPeriods() の純粋なオーバーラップ判定ロジックを検証する。
 * Google APIへの実通信は行わない(この部分は外部SDKから独立して切り出された関数)。
 */
class GoogleCalendarServiceTest extends TestCase
{
    public function test_returns_empty_array_when_no_busy_periods(): void
    {
        $timeMin = Carbon::parse('2026-10-01 00:00:00');
        $timeMax = Carbon::parse('2026-10-01 23:59:59');

        $keys = GoogleCalendarService::busyKeysFromPeriods([], $timeMin, $timeMax);

        $this->assertSame([], $keys);
    }

    public function test_period_fully_within_one_slot_marks_that_slot_busy(): void
    {
        $timeMin = Carbon::parse('2026-10-01 00:00:00');
        $timeMax = Carbon::parse('2026-10-01 23:59:59');
        $periods = [
            ['start' => '2026-10-01T10:15:00+09:00', 'end' => '2026-10-01T10:45:00+09:00'],
        ];

        $keys = GoogleCalendarService::busyKeysFromPeriods($periods, $timeMin, $timeMax);

        $this->assertSame(['10:00'], $keys);
    }

    public function test_period_spanning_multiple_slots_marks_all_overlapping_slots(): void
    {
        $timeMin = Carbon::parse('2026-10-01 00:00:00');
        $timeMax = Carbon::parse('2026-10-01 23:59:59');
        $periods = [
            ['start' => '2026-10-01T10:30:00+09:00', 'end' => '2026-10-01T12:30:00+09:00'],
        ];

        $keys = GoogleCalendarService::busyKeysFromPeriods($periods, $timeMin, $timeMax);

        $this->assertSame(['10:00', '11:00', '12:00'], $keys);
    }

    public function test_period_touching_slot_boundary_exactly_does_not_mark_it_busy(): void
    {
        $timeMin = Carbon::parse('2026-10-01 00:00:00');
        $timeMax = Carbon::parse('2026-10-01 23:59:59');
        // 11:00ちょうどに終わる予定は 10:00-11:00 スロットとは接するだけで重ならない
        $periods = [
            ['start' => '2026-10-01T09:00:00+09:00', 'end' => '2026-10-01T10:00:00+09:00'],
        ];

        $keys = GoogleCalendarService::busyKeysFromPeriods($periods, $timeMin, $timeMax);

        $this->assertSame(['09:00'], $keys);
    }

    public function test_duplicate_slots_across_periods_are_deduplicated(): void
    {
        $timeMin = Carbon::parse('2026-10-01 00:00:00');
        $timeMax = Carbon::parse('2026-10-01 23:59:59');
        $periods = [
            ['start' => '2026-10-01T10:00:00+09:00', 'end' => '2026-10-01T10:30:00+09:00'],
            ['start' => '2026-10-01T10:15:00+09:00', 'end' => '2026-10-01T10:45:00+09:00'],
        ];

        $keys = GoogleCalendarService::busyKeysFromPeriods($periods, $timeMin, $timeMax);

        $this->assertSame(['10:00'], $keys);
    }
}

<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Enrollment;
use App\Services\MeetingAvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * 予約画面が呼ぶ空き枠取得ユースケース。指定日の資格別スロットと空きコーチ数を返す。
 */
final class FetchAvailabilityAction
{
    public function __construct(
        private readonly MeetingAvailabilityService $availabilityService,
    ) {}

    /**
     * @return Collection<int, array{slot_start: Carbon, slot_end: Carbon, available_coach_count: int}>
     */
    public function __invoke(Enrollment $enrollment, Carbon $date): Collection
    {
        return $this->availabilityService->slotsForCertification(
            $enrollment->loadMissing('certification')->certification,
            $date,
        );
    }
}

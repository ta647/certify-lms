<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MeetingReserved;
use App\Notifications\MeetingReservedNotification;
use App\Services\NotificationRecipientFilter;

/**
 * 面談予約イベントを受けて、担当コーチへ通知する(受講生本人には発火しない)。
 * キュー化はしない(同期送信)。
 */
final class SendMeetingReservedNotification
{
    public function handle(MeetingReserved $event): void
    {
        $coach = $event->meeting->loadMissing('coach')->coach;

        if ($coach === null || ! NotificationRecipientFilter::isEligible($coach)) {
            return;
        }

        $coach->notify(new MeetingReservedNotification($event->meeting));
    }
}

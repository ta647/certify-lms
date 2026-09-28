<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MeetingCanceled;
use App\Services\GoogleCalendarService;

/**
 * 面談キャンセルイベントを受けて、Googleカレンダーに登録済の予定があれば連動削除する。
 * 未連携コーチ、またはそもそも登録されていない(google_calendar_event_id が null)場合は何もしない。
 * キュー化はしない(同期送信、通知リスナーと同じ方針)。
 */
final class RemoveMeetingFromGoogleCalendar
{
    public function __construct(private readonly GoogleCalendarService $googleCalendar) {}

    public function handle(MeetingCanceled $event): void
    {
        $meeting = $event->meeting->loadMissing('coach.googleCredential');

        $credential = $meeting->coach?->googleCredential;
        if ($credential === null || $meeting->google_calendar_event_id === null) {
            return;
        }

        $this->googleCalendar->deleteEvent($credential, $meeting->google_calendar_event_id);
    }
}

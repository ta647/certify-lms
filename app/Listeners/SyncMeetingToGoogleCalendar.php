<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MeetingReserved;
use App\Services\GoogleCalendarService;

/**
 * 面談予約イベントを受けて、担当コーチがGoogleカレンダー連携済であればその予定を自動登録する。
 * 未連携コーチには何もしない。API失敗時もGoogleCalendarService側で握りつぶされるため、
 * 面談予約自体は失敗しない。キュー化はしない(同期送信、通知リスナーと同じ方針)。
 */
final class SyncMeetingToGoogleCalendar
{
    public function __construct(private readonly GoogleCalendarService $googleCalendar) {}

    public function handle(MeetingReserved $event): void
    {
        $meeting = $event->meeting->loadMissing('coach.googleCredential');

        if ($meeting->coach?->googleCredential === null) {
            return;
        }

        $eventId = $this->googleCalendar->createEvent($meeting);

        if ($eventId !== null) {
            $meeting->update(['google_calendar_event_id' => $eventId]);
        }
    }
}

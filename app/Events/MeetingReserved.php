<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Meeting;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 面談が予約された際に発火するイベント。
 * 通知リスナー(SendMeetingReservedNotification)が担当コーチへの通知発火に利用する。
 */
final class MeetingReserved
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Meeting $meeting) {}
}

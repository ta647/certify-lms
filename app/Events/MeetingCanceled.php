<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 面談がキャンセルされた際に発火するイベント。
 * 通知リスナー(SendMeetingCanceledNotification)がキャンセル実行者の相手方への通知発火に利用する。
 */
final class MeetingCanceled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Meeting $meeting,
        public readonly User $actor,
    ) {}
}

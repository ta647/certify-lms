<?php

declare(strict_types=1);

namespace App\Enums;

enum MeetingReminderWindow: string
{
    case Eve = 'eve';
    case OneHourBefore = 'one_hour_before';

    /**
     * このウィンドウの送信済みフラグを保持する `meetings` テーブルのカラム名。
     * Model の対象抽出スコープと UseCase の冪等チェックの両方から参照される単一の対応表。
     */
    public function sentAtColumn(): string
    {
        return match ($this) {
            self::Eve => 'eve_reminder_sent_at',
            self::OneHourBefore => 'one_hour_reminder_sent_at',
        };
    }
}

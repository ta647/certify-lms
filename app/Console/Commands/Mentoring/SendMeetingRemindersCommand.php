<?php

declare(strict_types=1);

namespace App\Console\Commands\Mentoring;

use App\Enums\MeetingReminderWindow;
use App\Models\Meeting;
use App\UseCases\Meeting\SendMeetingReminderAction;
use Illuminate\Console\Command;

/**
 * 予約済み面談に対して前日 / 開始1時間前のリマインダーを配信する Schedule Command。
 *
 * `--window=eve` は毎日1回、`--window=one_hour_before` は数分間隔で起動する想定。
 * SendMeetingReminderAction が行レベルロック + 状態再確認で二重送信を防ぐ(冪等)。
 */
class SendMeetingRemindersCommand extends Command
{
    protected $signature = 'notifications:send-meeting-reminders {--window=}';

    protected $description = '予約済み面談の前日/1時間前リマインダーを配信する';

    public function handle(SendMeetingReminderAction $action): int
    {
        $window = MeetingReminderWindow::tryFrom((string) $this->option('window'));

        if ($window === null) {
            $this->error('無効な --window オプションです。eve または one_hour_before を指定してください。');

            return self::FAILURE;
        }

        $count = 0;

        Meeting::query()
            ->dueForReminder($window)
            ->orderBy('id')
            ->chunk(100, function ($meetings) use ($action, $window, &$count): void {
                foreach ($meetings as $meeting) {
                    $action($meeting, $window);
                    $count++;
                }
            });

        $this->info("リマインダーを {$count} 件処理しました。(window={$window->value})");

        return self::SUCCESS;
    }
}

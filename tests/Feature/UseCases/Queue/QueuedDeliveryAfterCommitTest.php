<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Queue;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * T-A-05: キューに積む通知・メールは DB トランザクション commit 後に初めて実際にpushされ、
 * rollback時は送信が漏れない(=jobsテーブルに行が残らない)ことを検証する。
 *
 * `after_commit` の実際の挙動(トランザクションが本当にcommit/rollbackされたかどうか)を
 * 検証する必要があるため、本テストでは `RefreshDatabase` を使わない(同トレイトはテスト全体を
 * 1つのトランザクションで包んで最後にrollbackする実装のため、テスト内の DB::transaction() が
 * 常にネストされたsavepointになってしまい、「本当にcommitされたか」を検証できない)。
 * 代わりに作成した行を個別に削除してクリーンアップする。
 */
class QueuedDeliveryAfterCommitTest extends TestCase
{
    private array $userIds = [];

    private array $announcementIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'database', 'queue.connections.database.after_commit' => true]);
        DB::table('jobs')->truncate();
    }

    protected function tearDown(): void
    {
        DB::table('jobs')->truncate();
        if ($this->userIds !== []) {
            DB::table('notifications')->whereIn('notifiable_id', $this->userIds)->delete();
        }
        // FK制約(announcements.created_by_user_id restrictOnDelete)のため、
        // 先にAnnouncementを削除してからUserを削除する。
        if ($this->announcementIds !== []) {
            Announcement::whereIn('id', $this->announcementIds)->delete();
        }
        if ($this->userIds !== []) {
            User::whereIn('id', $this->userIds)->delete();
        }

        parent::tearDown();
    }

    public function test_queued_notification_is_not_pushed_until_transaction_commits(): void
    {
        [$user, $announcement] = $this->makeUserAndAnnouncement();

        DB::transaction(function () use ($user, $announcement) {
            $user->notify(new AnnouncementNotification($announcement));

            $this->assertSame(0, DB::table('jobs')->count(), 'commit前はジョブがまだpushされていないはず');
        });

        // AnnouncementNotification::via() は ['mail', 'database'] を返すため、チャネルごとに1ジョブ=2件積まれる
        $this->assertSame(2, DB::table('jobs')->count(), 'commit後にジョブがpushされるはず');
    }

    public function test_queued_notification_is_discarded_when_transaction_rolls_back(): void
    {
        [$user, $announcement] = $this->makeUserAndAnnouncement();

        try {
            DB::transaction(function () use ($user, $announcement) {
                $user->notify(new AnnouncementNotification($announcement));

                throw new RuntimeException('テスト用の強制rollback');
            });
        } catch (RuntimeException) {
            // 想定内: rollbackさせるための例外
        }

        $this->assertSame(0, DB::table('jobs')->count(), 'rollback時は送信が漏れない(=ジョブが積まれない)はず');
    }

    public function test_worker_processes_queued_notification_and_delivers_database_channel(): void
    {
        [$user, $announcement] = $this->makeUserAndAnnouncement();

        $user->notify(new AnnouncementNotification($announcement));
        $this->assertSame(2, DB::table('jobs')->count(), '事前条件: mail/database 2チャネル分が積まれているはず');

        // worker 1台分の処理を2ジョブ分実行する(キュー自体はDBベースなので実通信は発生しない)
        $worker = app('queue.worker');
        $options = new WorkerOptions;
        $worker->runNextJob('database', 'default', $options);
        $worker->runNextJob('database', 'default', $options);

        $this->assertSame(0, DB::table('jobs')->count(), 'worker が処理した後はjobsテーブルから消えるはず');
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => AnnouncementNotification::class,
        ]);
    }

    /**
     * @return array{0: User, 1: Announcement}
     */
    private function makeUserAndAnnouncement(): array
    {
        $user = User::factory()->student()->create();
        $this->userIds[] = $user->id;

        $announcement = Announcement::factory()->create();
        $this->announcementIds[] = $announcement->id;
        $this->userIds[] = $announcement->created_by_user_id; // factoryが自動生成するadmin

        return [$user, $announcement];
    }
}

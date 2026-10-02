<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * T-A-05: リトライ上限を超えた送信は失敗ジョブとして記録し、後から再投入できることを検証する。
 *
 * 実際の通知クラス(確実に失敗させるのが難しい)ではなく、必ず失敗するテスト専用の最小 Job
 * (`AlwaysFailingTestJob`、本ファイル末尾で定義)でキュー基盤そのものの挙動を検証する。
 * バックオフは待ち時間ゼロにしてテストを高速に保つ(段階的バックオフの値自体は
 * QueuedNotificationsRetryPolicyTest で別途検証済み)。
 *
 * ジョブの実処理には `Artisan::call('queue:work', ...)` ではなく `Illuminate\Queue\Worker::runNextJob()`
 * を直接呼ぶ(daemon向けの`queue:work`コマンドをテストプロセス内で繰り返し呼ぶと、スイート全体を
 * 通して実行した場合にのみ処理されない不安定さが確認されたため、単発処理に適したAPIを直接使う)。
 * `JobFailed` → `failed_jobs` への記録は本来 `WorkCommand::listenForEvents()` が配線しているため、
 * `queue:work` を経由しない本テストでは同じリスナーを明示的に登録する。
 */
class FailedJobRetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'database']);
        DB::table('jobs')->truncate();
        DB::table('failed_jobs')->truncate();

        app('events')->listen(JobFailed::class, function (JobFailed $event) {
            app('queue.failer')->log(
                $event->connectionName,
                $event->job->getQueue(),
                $event->job->getRawBody(),
                $event->exception,
            );
        });
    }

    protected function tearDown(): void
    {
        DB::table('jobs')->truncate();
        DB::table('failed_jobs')->truncate();
        parent::tearDown();
    }

    public function test_job_exhausting_retries_is_recorded_as_failed_job(): void
    {
        AlwaysFailingTestJob::dispatch();
        $this->assertSame(1, DB::table('jobs')->count());

        $this->runJobUntilExhausted();

        $this->assertSame(0, DB::table('jobs')->count(), '3回とも失敗した後はjobsテーブルから無くなるはず');
        $this->assertSame(1, DB::table('failed_jobs')->count(), 'リトライを使い切るとfailed_jobsに記録されるはず');
    }

    public function test_failed_job_can_be_requeued_via_queue_retry_command(): void
    {
        AlwaysFailingTestJob::dispatch();
        $this->runJobUntilExhausted();
        $this->assertSame(1, DB::table('failed_jobs')->count(), '事前条件: 1件failed_jobsに記録されているはず');

        $exitCode = Artisan::call('queue:retry', ['id' => ['all']]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(0, DB::table('failed_jobs')->count(), '再投入後はfailed_jobsから消えるはず');
        $this->assertSame(1, DB::table('jobs')->count(), '再投入でjobsテーブルに戻るはず');
    }

    /**
     * AlwaysFailingTestJob(tries=3, backoff=[0,0])を、失敗し尽くしてfailed_jobsに記録されるまで処理する。
     */
    private function runJobUntilExhausted(): void
    {
        $worker = app('queue.worker');
        $options = new WorkerOptions;

        for ($i = 0; $i < 3; $i++) {
            $worker->runNextJob('database', 'default', $options);
        }
    }
}

/**
 * 必ず失敗するテスト専用Job。バックオフ・tries以外のロジックは持たない。
 */
class AlwaysFailingTestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(): void
    {
        throw new RuntimeException('テスト用: 常に失敗するジョブ');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [0, 0]; // テストを高速に保つため待ち時間なし
    }
}

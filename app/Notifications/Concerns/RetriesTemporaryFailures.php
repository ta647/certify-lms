<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

/**
 * キュー化した通知・メール送信共通のリトライ方針。
 *
 * 一時的な送信失敗(メールサーバの不調 / 外部APIの一時的なエラー等)に備え、段階的な待機を挟んで
 * 最大2回まで自動リトライする(計3回試行)。すべて失敗した場合は `failed_jobs` に記録され、
 * `sail artisan queue:retry {id}` で後から再投入できる。
 *
 * `Illuminate\Notifications\SendQueuedNotifications` / `Illuminate\Mail\SendQueuedMailable` は、
 * 対象オブジェクトに `$tries` プロパティ・`backoff()` メソッドがあればそれを優先して使うため、
 * 通知(Notification)・メール(Mailable)のどちらに使っても同様に効く。
 */
trait RetriesTemporaryFailures
{
    public int $tries = 3;

    /**
     * 1回目の失敗から30秒後、2回目の失敗から5分後にリトライする。
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 300];
    }
}

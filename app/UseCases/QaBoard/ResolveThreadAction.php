<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;

/**
 * 投稿者本人によるスレッドの解決済マーク。resolved_at に確定時刻を記録する。
 */
final class ResolveThreadAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        $thread->update([
            'status' => QaThreadStatus::Resolved,
            'resolved_at' => now(),
        ]);

        return $thread;
    }
}

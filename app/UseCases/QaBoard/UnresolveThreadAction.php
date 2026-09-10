<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;

/**
 * 投稿者本人によるスレッドの未解決への差し戻し。resolved_at をクリアする。
 */
final class UnresolveThreadAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        $thread->update([
            'status' => QaThreadStatus::Unresolved,
            'resolved_at' => null,
        ]);

        return $thread;
    }
}

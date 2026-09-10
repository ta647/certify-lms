<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Models\QaThread;

/**
 * 投稿者本人によるスレッド編集。資格(certification_id)は変更不可のため title / body のみ更新する。
 */
final class UpdateThreadAction
{
    /**
     * @param array{title: string, body: string} $data
     */
    public function __invoke(QaThread $thread, array $data): QaThread
    {
        $thread->update([
            'title' => $data['title'],
            'body' => $data['body'],
        ]);

        return $thread;
    }
}

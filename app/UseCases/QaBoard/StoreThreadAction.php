<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\Models\User;

/**
 * 受講生による質問スレッドの新規投稿。状態は常に Unresolved から開始する。
 */
final class StoreThreadAction
{
    /**
     * @param array{certification_id: string, title: string, body: string} $data
     */
    public function __invoke(User $author, array $data): QaThread
    {
        return QaThread::create([
            'certification_id' => $data['certification_id'],
            'user_id' => $author->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'status' => QaThreadStatus::Unresolved,
        ]);
    }
}

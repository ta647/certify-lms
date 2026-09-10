<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

/**
 * 受講生・コーチによる回答の新規投稿。
 */
final class StoreReplyAction
{
    /**
     * @param array{body: string} $data
     */
    public function __invoke(QaThread $thread, User $author, array $data): QaReply
    {
        return $thread->replies()->create([
            'user_id' => $author->id,
            'body' => $data['body'],
        ]);
    }
}

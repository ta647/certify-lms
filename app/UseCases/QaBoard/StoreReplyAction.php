<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Events\QaReplyPosted;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

/**
 * 受講生・コーチによる回答の新規投稿。投稿後に QaReplyPosted イベントを発火し、
 * スレッド投稿者への通知(S-B-04)につなげる。
 */
final class StoreReplyAction
{
    /**
     * @param array{body: string} $data
     */
    public function __invoke(QaThread $thread, User $author, array $data): QaReply
    {
        $reply = $thread->replies()->create([
            'user_id' => $author->id,
            'body' => $data['body'],
        ]);

        event(new QaReplyPosted($thread, $reply));

        return $reply;
    }
}

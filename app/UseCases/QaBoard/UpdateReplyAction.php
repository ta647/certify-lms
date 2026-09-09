<?php

declare(strict_types=1);

namespace App\UseCases\QaBoard;

use App\Models\QaReply;

/**
 * 投稿者本人による回答編集。
 */
final class UpdateReplyAction
{
    /**
     * @param array{body: string} $data
     */
    public function __invoke(QaReply $reply, array $data): QaReply
    {
        $reply->update(['body' => $data['body']]);

        return $reply;
    }
}

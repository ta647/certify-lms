<?php

declare(strict_types=1);

namespace App\Exceptions\QaBoard;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 回答が付いているスレッドを投稿者本人が削除しようとした際の例外(HTTP 409)。
 *
 * 集合知として蓄積された回答付きスレッドは投稿者本人でも削除できず、以後は管理者のモデレーション削除のみ可能。
 * `QaThreadController::destroy` が「投稿者本人 + 回答 0 件」のドメインルールから throw する。
 */
final class ThreadHasRepliesException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('回答が付いているスレッドは削除できません。', $previous);
    }
}

<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 削除条件を満たさないプランを削除しようとした際の例外(HTTP 409)。
 * `Plan\DestroyAction` が「下書き かつ 受講者0名」のドメインルールから throw する。
 * 実際の DELETE 文が user_plan_logs の外部キー制約(RESTRICT)で失敗した場合も、
 * 同じ例外に変換して生の SQL エラーを隠蔽する。
 */
final class PlanNotDeletableException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('下書き かつ 受講者が0名のプランのみ削除できます。', $previous);
    }
}

<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanNotDeletableException;
use App\Models\Plan;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * プランを物理削除するユースケース。「下書き かつ 受講者0名」の場合のみ削除可能。
 *
 * 過去にそのプランを使っていたユーザーの履歴(user_plan_logs)が残っている場合、
 * アプリ側の事前チェックを通過しても DB の外部キー制約(RESTRICT)で実 DELETE が失敗し得るため、
 * その QueryException も同じドメイン例外に変換して生の SQL エラーを隠蔽する。
 */
final class DestroyAction
{
    /**
     * @throws PlanNotDeletableException
     */
    public function __invoke(Plan $plan): void
    {
        if ($plan->status !== PlanStatus::Draft || $plan->users()->count() > 0) {
            throw new PlanNotDeletableException;
        }

        try {
            DB::transaction(fn () => $plan->delete());
        } catch (QueryException $e) {
            throw new PlanNotDeletableException($e);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * admin 用のプラン一覧をフィルタ付きで取得するユースケース。
 * 一覧の受講者数表示のため `withCount('users')` を付与する。
 */
final class IndexAction
{
    public function __invoke(?string $keyword, ?PlanStatus $status, int $perPage = 20): LengthAwarePaginator
    {
        return Plan::query()
            ->withCount('users')
            ->keyword($keyword)
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->ordered()
            ->paginate($perPage)
            ->withQueryString();
    }
}

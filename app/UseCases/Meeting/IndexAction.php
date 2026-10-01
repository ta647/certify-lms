<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 受講生本人の面談一覧を取得するユースケース。filter (upcoming/past/all) で履歴を切り替える。
 */
final class IndexAction
{
    public function __invoke(User $student, string $filter = 'upcoming', int $perPage = 20): LengthAwarePaginator
    {
        $query = Meeting::query()
            ->with(['enrollment.certification', 'coach'])
            ->forStudent($student)
            ->orderByDesc('scheduled_at');

        return match ($filter) {
            'past' => $query->past()->paginate($perPage),
            'all' => $query->paginate($perPage),
            default => $query->upcoming()->paginate($perPage),
        };
    }
}

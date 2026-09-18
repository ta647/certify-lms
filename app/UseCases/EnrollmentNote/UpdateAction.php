<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\EnrollmentNote;

/**
 * 作成者本人または管理者による業務記録メモの編集。
 */
final class UpdateAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(EnrollmentNote $note, array $validated): EnrollmentNote
    {
        $note->update(['body' => $validated['body']]);

        return $note->fresh();
    }
}

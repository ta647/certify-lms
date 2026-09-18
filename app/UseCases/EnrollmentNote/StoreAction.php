<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

/**
 * コーチ・管理者による業務記録メモの新規追加。
 */
final class StoreAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(Enrollment $enrollment, User $author, array $validated): EnrollmentNote
    {
        return $enrollment->notes()->create([
            'user_id' => $author->id,
            'body' => $validated['body'],
        ]);
    }
}

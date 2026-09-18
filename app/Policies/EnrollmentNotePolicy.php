<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

/**
 * EnrollmentNote リソースに対する認可ポリシー。
 *
 * - viewAny/create: 管理者は常に許可。コーチは担当資格(`coachingCertificationIds()`)の受講登録のみ許可。
 *   受講生は常に不許可(メモのセクション自体が現れない)
 * - update/delete: 作成者本人、または管理者のみ許可(他コーチは閲覧のみで操作不可)
 */
class EnrollmentNotePolicy
{
    public function viewAny(User $user, Enrollment $enrollment): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->role === UserRole::Coach
            && in_array($enrollment->certification_id, $user->coachingCertificationIds(), true);
    }

    public function create(User $user, Enrollment $enrollment): bool
    {
        return $this->viewAny($user, $enrollment);
    }

    public function update(User $user, EnrollmentNote $note): bool
    {
        return $user->role === UserRole::Admin || $user->id === $note->user_id;
    }

    public function delete(User $user, EnrollmentNote $note): bool
    {
        return $user->role === UserRole::Admin || $user->id === $note->user_id;
    }
}

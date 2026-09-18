<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;

/**
 * EnrollmentGoal リソースに対する認可ポリシー。
 *
 * 全操作、受講登録(Enrollment)の本人(受講生)のみが実行可能。コーチ/管理者/他受講生は
 * 閲覧はできる(受講登録詳細画面のPolicyに委ねる)が、目標自体の操作は一切できない。
 */
class EnrollmentGoalPolicy
{
    public function create(User $user, Enrollment $enrollment): bool
    {
        return $user->id === $enrollment->user_id;
    }

    public function update(User $user, EnrollmentGoal $goal): bool
    {
        return $user->id === $goal->enrollment->user_id;
    }

    public function delete(User $user, EnrollmentGoal $goal): bool
    {
        return $user->id === $goal->enrollment->user_id;
    }

    public function markAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $user->id === $goal->enrollment->user_id && $goal->achieved_at === null;
    }

    public function unmarkAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $user->id === $goal->enrollment->user_id && $goal->achieved_at !== null;
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;

/**
 * Plan リソースに対する認可ポリシー。
 *
 * 全操作が管理者専用のため、各メソッドは役割チェックのみを行う。
 * 「今の状態で実行してよいか」という業務ルールはここでは扱わず、
 * `App\UseCases\Plan\*` 内でドメイン例外(409)として判定する。
 */
class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function publish(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function archive(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function unarchive(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::Admin;
    }
}

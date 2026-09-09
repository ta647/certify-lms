<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;

/**
 * QaThread リソースに対する認可ポリシー。
 *
 * - viewAny: admin / coach / student いずれも一覧自体は閲覧可(取得スコープは QaThread::scopeVisibleTo で絞る)
 * - view: admin は全件、student は公開中資格なら無条件、coach は公開中かつ担当資格のみ
 * - create: 投稿(スレッド新規作成)は student のみ
 * - update: 投稿者本人のみ(資格の変更は不可、Controller/Request 側で制御)
 * - delete: admin は任意のスレッドを削除可。投稿者本人は「まだ回答が付いていない」スレッドのみ削除可
 *   (集合知として蓄積された回答付きスレッドは以後モデレーション削除のみ)
 * - resolve / unresolve: 投稿者本人のみ、かつ現在の状態と矛盾しない場合のみ
 */
class QaThreadPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Coach, UserRole::Student], true);
    }

    public function view(User $user, QaThread $thread): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        $thread->loadMissing('certification');

        if ($thread->certification?->status !== CertificationStatus::Published) {
            return false;
        }

        if ($user->role === UserRole::Coach) {
            return in_array($thread->certification_id, $user->coachingCertificationIds(), true);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    public function update(User $user, QaThread $thread): bool
    {
        return $user->id === $thread->user_id;
    }

    public function delete(User $user, QaThread $thread): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        if ($user->id !== $thread->user_id) {
            return false;
        }

        return $thread->loadCount('replies')->replies_count === 0;
    }

    public function resolve(User $user, QaThread $thread): bool
    {
        return $user->id === $thread->user_id && $thread->status === QaThreadStatus::Unresolved;
    }

    public function unresolve(User $user, QaThread $thread): bool
    {
        return $user->id === $thread->user_id && $thread->status === QaThreadStatus::Resolved;
    }
}

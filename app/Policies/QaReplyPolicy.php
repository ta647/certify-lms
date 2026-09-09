<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

/**
 * QaReply リソースに対する認可ポリシー。
 *
 * - create: admin は回答不可。student / coach は対象スレッドが QaThreadPolicy::view で閲覧可能な場合のみ
 * - update: 投稿者本人のみ
 * - delete: admin は任意の回答を削除可。それ以外は投稿者本人のみ
 */
class QaReplyPolicy
{
    public function create(User $user, QaThread $thread): bool
    {
        if ($user->role === UserRole::Admin) {
            return false;
        }

        return app(QaThreadPolicy::class)->view($user, $thread);
    }

    public function update(User $user, QaReply $reply): bool
    {
        return $user->id === $reply->user_id;
    }

    public function delete(User $user, QaReply $reply): bool
    {
        return $user->role === UserRole::Admin || $user->id === $reply->user_id;
    }
}

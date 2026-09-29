<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AiChatConversation;
use App\Models\User;

/**
 * AI相談の会話(AiChatConversation)に対する認可ポリシー。
 *
 * 会話はオーナー本人のみが閲覧・操作できる(PM回答Q2/Q3にもあるとおり、他受講生の会話は一切拾わない)。
 * ロール・利用状態(学習中の受講生のみ)は `role:student` + `active-learning` ミドルウェアで別途保証する。
 */
class AiChatConversationPolicy
{
    public function view(User $user, AiChatConversation $conversation): bool
    {
        return $conversation->user_id === $user->id;
    }

    public function update(User $user, AiChatConversation $conversation): bool
    {
        return $conversation->user_id === $user->id;
    }

    public function delete(User $user, AiChatConversation $conversation): bool
    {
        return $conversation->user_id === $user->id;
    }
}

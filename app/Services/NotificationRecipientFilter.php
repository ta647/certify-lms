<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

/**
 * 通知の配信対象として適格かどうかを判定する共通フィルタ。
 *
 * 利用状態が「受講中(in_progress)」以外(招待中/卒業/退会)のユーザーはロールを問わず配信対象外。
 * 管理者はいずれの業務通知(chat/QA/面談)も対象外。
 */
final class NotificationRecipientFilter
{
    public static function isEligible(User $user): bool
    {
        return $user->status === UserStatus::InProgress
            && $user->role !== UserRole::Admin;
    }
}

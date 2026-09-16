<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * プロフィール情報(氏名 / 自己紹介 / 固定面談URL)を更新するユースケース。
 * meeting_url はコーチのみ更新対象とし、それ以外のロールからの入力は無視する。
 */
final class UpdateProfileAction
{
    /**
     * @param array{name: string, bio?: ?string, meeting_url?: ?string} $validated
     */
    public function __invoke(User $user, array $validated): User
    {
        return DB::transaction(function () use ($user, $validated) {
            $attributes = [
                'name' => $validated['name'],
                'bio' => $validated['bio'] ?? null,
            ];

            if ($user->role === UserRole::Coach) {
                $attributes['meeting_url'] = $validated['meeting_url'] ?? null;
            }

            $user->update($attributes);

            return $user->fresh();
        });
    }
}

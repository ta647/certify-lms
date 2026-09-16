<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * アバター画像を削除するユースケース。`avatar_url` を null に戻し、commit 後に実ファイルを削除する。
 */
final class DestroyAvatarAction
{
    public function __invoke(User $user): void
    {
        DB::transaction(function () use ($user) {
            $url = $user->avatar_url;

            $user->update(['avatar_url' => null]);

            if ($url !== null) {
                DB::afterCommit(fn () => Storage::disk('public')->delete($this->pathFromUrl($url)));
            }
        });
    }

    private function pathFromUrl(string $url): string
    {
        return ltrim(str_replace('/storage/', '', $url), '/');
    }
}

<?php

declare(strict_types=1);

namespace App\UseCases\Settings;

use App\Exceptions\Settings\AvatarStorageException;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * アバター画像のアップロードユースケース。
 *
 * `avatars/{ulid}.{ext}` 形式で public disk に保存し、`avatar_url` に表示用パス(`/storage/avatars/...`)を
 * そのまま保存する(`<x-avatar :src>` が値を直接 <img src> に使うため)。
 * Storage 保存と DB UPDATE を単一トランザクション内で実行し、失敗時は保険的に Storage を削除する。
 * 差し替え(既存アバターがある状態での再アップロード)の場合、commit 後に古いファイルを削除する。
 */
final class StoreAvatarAction
{
    /**
     * @throws AvatarStorageException
     */
    public function __invoke(User $user, UploadedFile $file): User
    {
        $ulid = (string) Str::ulid();
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $path = "avatars/{$ulid}.{$ext}";
        $url = "/storage/{$path}";

        try {
            return DB::transaction(function () use ($user, $file, $path, $url, $ulid, $ext) {
                $oldUrl = $user->avatar_url;

                Storage::disk('public')->putFileAs('avatars', $file, "{$ulid}.{$ext}");

                $user->update(['avatar_url' => $url]);

                if ($oldUrl !== null) {
                    DB::afterCommit(fn () => Storage::disk('public')->delete($this->pathFromUrl($oldUrl)));
                }

                return $user->fresh();
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw new AvatarStorageException($e);
        }
    }

    private function pathFromUrl(string $url): string
    {
        return ltrim(str_replace('/storage/', '', $url), '/');
    }
}

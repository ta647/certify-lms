<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AvatarStoreRequest;
use App\UseCases\Settings\DestroyAvatarAction;
use App\UseCases\Settings\StoreAvatarAction;
use Illuminate\Http\RedirectResponse;

/**
 * アバター画像のアップロード / 削除 Controller。常に自分自身のアバターのみを操作する。
 */
class AvatarController extends Controller
{
    public function store(AvatarStoreRequest $request, StoreAvatarAction $action): RedirectResponse
    {
        $action($request->user(), $request->file('avatar'));

        return redirect()
            ->route('settings.profile.edit')
            ->with('success', 'アイコン画像を更新しました。');
    }

    public function destroy(DestroyAvatarAction $action): RedirectResponse
    {
        $action(auth()->user());

        return redirect()
            ->route('settings.profile.edit')
            ->with('success', 'アイコン画像を削除しました。');
    }
}

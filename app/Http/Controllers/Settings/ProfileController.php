<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\UseCases\Settings\UpdateProfileAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * プロフィール設定画面(氏名 / 自己紹介 / 固定面談URL)の Controller。
 * 全ロール共通、`active-learning` 対象外(修了済ユーザーも利用可能)。
 */
class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('settings.profile', ['user' => auth()->user()]);
    }

    public function update(ProfileUpdateRequest $request, UpdateProfileAction $action): RedirectResponse
    {
        $action($request->user(), $request->validated());

        return redirect()
            ->route('settings.profile.edit')
            ->with('success', 'プロフィールを更新しました。');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Fortify\UpdateUserPassword;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * パスワード変更 Controller。
 *
 * バリデーション・現在パスワード照合は Fortify 標準の `UpdateUserPassword` アクションに委譲する
 * (`config/fortify.php` で `updatePasswords` 機能自体は無効化し、本 Controller から明示的に呼び出す設計)。
 * 検証エラーは `updatePassword` エラーバッグに入り、Blade 側もこれを前提に表示する。
 */
class PasswordController extends Controller
{
    public function update(Request $request, UpdateUserPassword $action): RedirectResponse
    {
        $action->update($request->user(), $request->all());

        return redirect()
            ->route('settings.profile.edit', ['tab' => 'password'])
            ->with('success', 'パスワードを変更しました。');
    }
}

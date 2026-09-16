<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * プロフィール情報(氏名 / 自己紹介 / 固定面談URL)の更新リクエスト。
 *
 * 自分自身の情報のみを更新する操作のため authorize() は常に true。
 * meeting_url はロールに関わらずルール上は許容し、コーチ以外からの入力は
 * `App\UseCases\Settings\UpdateProfileAction` 側で無視する。
 */
class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'meeting_url' => ['nullable', 'string', 'url', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '氏名',
            'bio' => '自己紹介',
            'meeting_url' => '固定面談URL',
        ];
    }
}

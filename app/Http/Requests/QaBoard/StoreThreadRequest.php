<?php

declare(strict_types=1);

namespace App\Http\Requests\QaBoard;

use App\Models\QaThread;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 質問スレッドの新規投稿。資格は「公開中」のもののみ選択可能(下書き / アーカイブは不可)。
 */
class StoreThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', QaThread::class) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'certification_id' => [
                'required',
                'ulid',
                Rule::exists('certifications', 'id')->where('status', 'published'),
            ],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}

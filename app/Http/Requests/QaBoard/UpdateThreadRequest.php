<?php

declare(strict_types=1);

namespace App\Http\Requests\QaBoard;

use App\Models\QaThread;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 投稿者本人によるスレッド編集。資格(certification_id)は変更不可のため受け付けない。
 */
class UpdateThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $thread = $this->route('thread');

        return $thread instanceof QaThread
            && $this->user()?->can('update', $thread) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}

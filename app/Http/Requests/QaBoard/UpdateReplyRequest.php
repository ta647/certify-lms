<?php

declare(strict_types=1);

namespace App\Http\Requests\QaBoard;

use App\Models\QaReply;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 投稿者本人による回答編集。
 */
class UpdateReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reply = $this->route('reply');

        return $reply instanceof QaReply
            && $this->user()?->can('update', $reply) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}

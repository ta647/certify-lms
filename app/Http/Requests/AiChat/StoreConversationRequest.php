<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AI相談の会話作成リクエスト。認可はrole:student + active-learningミドルウェアで担保済みのため常にtrue。
 * `source=widget`はJSONで会話のみ作成/再利用(section_id任意)、`source=full-screen`は常に新規作成し
 * `message`があれば初回メッセージまで同期送信する(HTMLフォーム経由)。
 */
class StoreConversationRequest extends FormRequest
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
            'source' => ['required', 'in:widget,full-screen'],
            'section_id' => ['nullable', 'ulid', 'exists:sections,id'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

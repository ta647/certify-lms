<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChat;

use App\Models\AiChatConversation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * AI相談メッセージの送信リクエスト(JSON専用エンドポイント)。オーナー本人のみ送信できる。
 */
class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('conversation');

        return $conversation instanceof AiChatConversation
            && ($this->user()?->can('view', $conversation) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }
}

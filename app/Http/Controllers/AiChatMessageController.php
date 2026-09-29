<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AiChat\StoreMessageRequest;
use App\Models\AiChatConversation;
use App\UseCases\AiChat\SendMessageAction;
use Illuminate\Http\JsonResponse;

/**
 * AI相談メッセージ送信(JSON専用)を扱うController。
 * 日次上限超過(429)/Gemini失敗(502)は例外自身のrender()が処理するため、ここでは捕捉しない。
 */
class AiChatMessageController extends Controller
{
    public function store(StoreMessageRequest $request, AiChatConversation $conversation, SendMessageAction $action): JsonResponse
    {
        $result = $action($conversation, $request->user(), $request->validated('content'));

        $payload = [
            'user_message' => $result['user_message'],
            'assistant_message' => $result['assistant_message'],
            'conversation' => ['id' => $result['conversation']->id],
        ];

        if ($result['title_updated']) {
            $payload['conversation']['title'] = $result['conversation']->title;
        }

        return response()->json($payload);
    }
}

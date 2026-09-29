<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 受講生の1日あたりのAI相談送信上限(config('ai-chat.daily_message_limit'))を超過した場合の例外。
 * 自前のrender()でHTTP 429を返す(chat-client.jsがステータスコードのみで判定する)。
 */
final class AiChatDailyLimitExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('本日の利用上限に達しました。');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => '本日の利用上限に達しました。明日 0:00 以降に再度ご利用ください。',
        ], 429);
    }
}

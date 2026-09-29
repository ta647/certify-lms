<?php

declare(strict_types=1);

namespace App\Exceptions\AiChat;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Gemini APIとの通信・応答が失敗したことを表す例外。
 *
 * 送出元(SendMessageAction)はJSON専用エンドポイント(ai-chat.conversations.messages.store)からのみ
 * 呼ばれる前提のため、中央のExceptions\Handlerには乗せず、自前のrender()でupstream_statusを含む
 * 502を返す(chat-client.jsがこのキーを読んで文言を出し分ける)。
 */
final class GeminiApiException extends RuntimeException
{
    public function __construct(string $message, private readonly ?int $upstreamStatus = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function upstreamStatus(): ?int
    {
        return $this->upstreamStatus;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'AIが応答できませんでした。',
            'upstream_status' => $this->upstreamStatus,
        ], 502);
    }
}

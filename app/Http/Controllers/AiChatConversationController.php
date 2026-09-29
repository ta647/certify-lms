<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\AiChat\AiChatDailyLimitExceededException;
use App\Http\Requests\AiChat\StoreConversationRequest;
use App\Http\Requests\AiChat\UpdateConversationRequest;
use App\Models\AiChatConversation;
use App\UseCases\AiChat\SendMessageAction;
use App\UseCases\AiChat\StoreConversationAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * AI相談の会話CRUDを扱うController。メッセージ送信は`AiChatMessageController`が別に担う。
 */
class AiChatConversationController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $latest = $request->user()->aiChatConversations()->orderByDesc('last_message_at')->first();

        if ($latest !== null) {
            return redirect()->route('ai-chat.conversations.show', $latest);
        }

        return view('ai-chat.empty-state');
    }

    /**
     * 二重契約: Accept: application/json(ウィジェット)は会話の作成/再利用のみを行いJSONで返す。
     * 通常のHTMLフォーム(新規相談モーダル)は常に新規作成し、`message`があれば初回送信まで同期実行してから
     * 会話詳細へリダイレクトする。
     */
    public function store(
        StoreConversationRequest $request,
        StoreConversationAction $storeConversation,
        SendMessageAction $sendMessage,
    ): JsonResponse|RedirectResponse {
        $user = $request->user();
        $conversation = $storeConversation($user, $request->validated('source'), $request->validated('section_id'));

        if ($request->wantsJson()) {
            return response()->json([
                'conversation' => ['id' => $conversation->id, 'title' => $conversation->title],
            ], $conversation->wasRecentlyCreated ? 201 : 200);
        }

        $message = $request->validated('message');
        if (is_string($message) && trim($message) !== '') {
            try {
                $sendMessage($conversation, $user, $message);
            } catch (Throwable $e) {
                return redirect()
                    ->route('ai-chat.conversations.show', $conversation)
                    ->with('error', $this->describeSendFailure($e));
            }
        }

        return redirect()->route('ai-chat.conversations.show', $conversation);
    }

    public function show(Request $request, AiChatConversation $conversation): View|JsonResponse
    {
        abort_unless($request->user()?->can('view', $conversation) ?? false, 403);

        if ($request->wantsJson()) {
            return response()->json([
                'messages' => $conversation->messages->map(fn ($m) => [
                    'role' => $m->role->value,
                    'content' => $m->content,
                    'status' => $m->status->value,
                ]),
            ]);
        }

        return view('ai-chat.show', ['conversation' => $conversation->load('messages')]);
    }

    public function update(UpdateConversationRequest $request, AiChatConversation $conversation): RedirectResponse
    {
        $conversation->update(['title' => $request->validated('title')]);

        return redirect()
            ->route('ai-chat.conversations.show', $conversation)
            ->with('success', 'タイトルを更新しました。');
    }

    public function destroy(Request $request, AiChatConversation $conversation): RedirectResponse
    {
        abort_unless($request->user()?->can('delete', $conversation) ?? false, 403);

        $conversation->delete();

        return redirect()->route('ai-chat.index')->with('success', '会話を削除しました。');
    }

    private function describeSendFailure(Throwable $e): string
    {
        if ($e instanceof AiChatDailyLimitExceededException) {
            return '本日の利用上限に達しました。明日 0:00 以降に再度ご利用ください。';
        }

        return 'AIが応答できませんでした。会話は作成されているので、下の入力欄から再度お試しください。';
    }
}

<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Enums\EnrollmentStatus;
use App\Exceptions\AiChat\AiChatDailyLimitExceededException;
use App\Exceptions\AiChat\GeminiApiException;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Services\GeminiClient;
use Illuminate\Support\Facades\DB;

/**
 * AI相談メッセージ送信の中核ユースケース。
 *
 * 1. 1日の送信上限を超えていないか確認(超過時は例外、メッセージは作成しない)
 * 2. 受講生の発言を保存
 * 3. Geminiへ同期問い合わせ(DBトランザクション外、外部通信を長時間ロック内に置かない)
 * 4. 成功: AI応答を保存。初回成功時のみタイトル自動生成(PM回答Q4)
 * 5. 失敗: AI応答をerror状態で保存し、質問自体は残す(受講生は送り直せる)。例外は呼出元へ伝播し、
 *    GeminiApiException自身のrender()が502を返す
 *
 * @throws AiChatDailyLimitExceededException
 * @throws GeminiApiException
 */
final class SendMessageAction
{
    public function __construct(private readonly GeminiClient $gemini) {}

    /**
     * @return array{user_message: AiChatMessage, assistant_message: AiChatMessage, conversation: AiChatConversation, title_updated: bool}
     */
    public function __invoke(AiChatConversation $conversation, User $student, string $content): array
    {
        $limit = (int) config('ai-chat.daily_message_limit');
        $sentToday = AiChatMessage::query()
            ->where('user_id', $student->id)
            ->where('role', AiChatMessageRole::User->value)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        if ($sentToday >= $limit) {
            throw new AiChatDailyLimitExceededException;
        }

        $conversation->loadMissing(['section', 'enrollment.certification']);

        $history = $conversation->messages()
            ->where(function ($q) {
                $q->where('role', AiChatMessageRole::User->value)
                    ->orWhere('status', AiChatMessageStatus::Completed->value);
            })
            ->get(['role', 'content'])
            ->map(fn (AiChatMessage $m) => ['role' => $m->role->value, 'content' => $m->content])
            ->all();

        $systemContext = $this->buildSystemContext($conversation, $student);

        [$userMessage, $assistantMessage] = DB::transaction(function () use ($conversation, $student, $content) {
            $userMessage = AiChatMessage::create([
                'ai_chat_conversation_id' => $conversation->id,
                'user_id' => $student->id,
                'role' => AiChatMessageRole::User->value,
                'status' => AiChatMessageStatus::Completed->value,
                'content' => $content,
            ]);

            $assistantMessage = AiChatMessage::create([
                'ai_chat_conversation_id' => $conversation->id,
                'user_id' => $student->id,
                'role' => AiChatMessageRole::Assistant->value,
                'status' => AiChatMessageStatus::Pending->value,
                'content' => '',
            ]);

            return [$userMessage, $assistantMessage];
        });

        try {
            $result = $this->gemini->generateReply($systemContext, $history, $content);
        } catch (GeminiApiException $e) {
            $assistantMessage->update([
                'status' => AiChatMessageStatus::Error->value,
                'error_detail' => $e->getMessage(),
            ]);
            $conversation->update(['last_message_at' => now()]);

            throw $e;
        }

        $assistantMessage->update([
            'status' => AiChatMessageStatus::Completed->value,
            'content' => $result['content'],
            'model' => $result['model'],
            'response_time_ms' => $result['response_time_ms'],
            'output_tokens' => $result['output_tokens'],
        ]);
        $conversation->update(['last_message_at' => now()]);

        $titleUpdated = false;
        if (! $conversation->auto_titled && (bool) config('ai-chat.auto_title')) {
            $title = $this->gemini->generateTitle($content, $result['content']);
            $conversation->update([
                'auto_titled' => true,
                'title' => $title ?? $conversation->title,
            ]);
            $titleUpdated = $title !== null;
        }

        return [
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage->fresh(),
            'conversation' => $conversation->fresh(),
            'title_updated' => $titleUpdated,
        ];
    }

    private function buildSystemContext(AiChatConversation $conversation, User $student): string
    {
        $lines = ['あなたはCertify LMSの学習相談AIです。受講生からの質問に日本語で簡潔かつ丁寧に回答してください。'];

        $certificationName = $this->resolveCertificationName($conversation, $student);
        if ($certificationName !== null) {
            $lines[] = "受講生は「{$certificationName}」を受講中です。";
        }

        $sectionTitle = $conversation->section?->title;
        if ($sectionTitle !== null) {
            $lines[] = "受講生は教材「{$sectionTitle}」を閲覧中です。";
        }

        return implode("\n", $lines);
    }

    /**
     * 資格文脈の解決(PM回答Q3): 会話に紐づく資格(教材由来)を優先し、無ければその時点のデフォルト資格。
     * いずれも学習中/修了の場合のみ採用する。
     */
    private function resolveCertificationName(AiChatConversation $conversation, User $student): ?string
    {
        $enrollment = $conversation->enrollment ?? $student->defaultEnrollment;

        if ($enrollment === null || ! in_array($enrollment->status, [EnrollmentStatus::Learning, EnrollmentStatus::Passed], true)) {
            return null;
        }

        return $enrollment->certification?->name;
    }
}

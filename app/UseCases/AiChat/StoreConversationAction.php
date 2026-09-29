<?php

declare(strict_types=1);

namespace App\UseCases\AiChat;

use App\Enums\EnrollmentStatus;
use App\Models\AiChatConversation;
use App\Models\Section;
use App\Models\User;

/**
 * AI相談の会話を作成/再利用するユースケース。
 *
 * PM回答(Q2)どおり、再利用対象はウィジェット経由(source=widget)のみ。
 * 同じ受講生×同じSectionコンテキスト(全般相談ならどちらもnull)の会話があれば
 * 最終メッセージが新しいものを再開し、無ければ新規作成する。
 * フル画面の「新しい相談」(source=full-screen)は常に新規作成する。
 *
 * 戻り値の `AiChatConversation::$wasRecentlyCreated` で、呼出元(Controller)が
 * 新規作成/既存再利用を判定できる(201 / 200 の出し分けに使う)。
 */
final class StoreConversationAction
{
    public function __invoke(User $user, string $source, ?string $sectionId): AiChatConversation
    {
        if ($source === 'widget') {
            $existing = AiChatConversation::query()
                ->where('user_id', $user->id)
                ->when(
                    $sectionId !== null,
                    fn ($q) => $q->where('section_id', $sectionId),
                    fn ($q) => $q->whereNull('section_id'),
                )
                ->orderByDesc('last_message_at')
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return AiChatConversation::create([
            'user_id' => $user->id,
            'section_id' => $sectionId,
            'enrollment_id' => $sectionId !== null ? $this->resolveEnrollmentId($user, $sectionId) : null,
            'title' => '新規相談',
        ]);
    }

    private function resolveEnrollmentId(User $user, string $sectionId): ?string
    {
        $section = Section::query()->with('chapter.part')->find($sectionId);
        $certificationId = $section?->chapter?->part?->certification_id;

        if ($certificationId === null) {
            return null;
        }

        return $user->enrollments()
            ->where('certification_id', $certificationId)
            ->whereIn('status', [EnrollmentStatus::Learning->value, EnrollmentStatus::Passed->value])
            ->value('id');
    }
}

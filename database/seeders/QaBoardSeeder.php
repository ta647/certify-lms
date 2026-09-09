<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * 開発用 qa-board シーダー。
 *
 * 公開中の資格ごとに、未解決 / 解決済・回答0件 / 数件・作成日時をばらつかせたスレッドを配置し、
 * 一覧の絞り込み・並び順・ページネーション・削除ボタンの表示条件(回答0件のみ削除可)を確認できるようにする。
 * さらに固定 student(`student@certify-lms.test`)を投稿者にしたスレッドを用意し、「自分の質問」動線を確認できるようにする。
 *
 * 依存順序: `UserSeeder` → `CertificationSeeder`(担当コーチ割当含む)→ 本 Seeder。
 */
final class QaBoardSeeder extends Seeder
{
    public function run(): void
    {
        $certifications = Certification::query()->published()->with('coaches')->get();
        $students = User::query()->where('role', UserRole::Student->value)->get();

        if ($certifications->isEmpty() || $students->isEmpty()) {
            $this->command?->warn('QaBoardSeeder: 公開中の資格または受講生が存在しません。先に CertificationSeeder / UserSeeder を実行してください。');

            return;
        }

        foreach ($certifications as $certification) {
            $this->seedThreadsForCertification($certification, $students);
        }

        $this->seedFixedStudentThreads($certifications);
    }

    /**
     * @param  Collection<int, User>  $students
     */
    private function seedThreadsForCertification(Certification $certification, Collection $students): void
    {
        $coach = $certification->coaches->first();

        $blueprints = [
            ['status' => QaThreadStatus::Unresolved, 'replyCount' => 0, 'daysAgo' => 1],
            ['status' => QaThreadStatus::Unresolved, 'replyCount' => 2, 'daysAgo' => 3],
            ['status' => QaThreadStatus::Resolved, 'replyCount' => 1, 'daysAgo' => 5],
            ['status' => QaThreadStatus::Resolved, 'replyCount' => 3, 'daysAgo' => 8],
            ['status' => QaThreadStatus::Unresolved, 'replyCount' => 1, 'daysAgo' => 12],
        ];

        foreach ($blueprints as $blueprint) {
            $author = $students->random();
            $createdAt = Carbon::now()->subDays($blueprint['daysAgo']);

            $thread = QaThread::create([
                'certification_id' => $certification->id,
                'user_id' => $author->id,
                'title' => fake()->sentence(8),
                'body' => fake()->realText(300),
                'status' => QaThreadStatus::Unresolved,
            ]);
            $thread->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

            $lastActivityAt = $createdAt;
            for ($i = 0; $i < $blueprint['replyCount']; $i++) {
                $replier = ($i === 0 && $coach !== null) ? $coach : $students->random();
                $lastActivityAt = $createdAt->copy()->addHours($i + 1);

                $reply = QaReply::create([
                    'qa_thread_id' => $thread->id,
                    'user_id' => $replier->id,
                    'body' => fake()->realText(150),
                ]);
                $reply->forceFill(['created_at' => $lastActivityAt, 'updated_at' => $lastActivityAt])->save();
            }

            if ($blueprint['status'] === QaThreadStatus::Resolved) {
                $resolvedAt = $lastActivityAt->copy()->addHour();
                $thread->forceFill([
                    'status' => QaThreadStatus::Resolved,
                    'resolved_at' => $resolvedAt,
                    'updated_at' => $resolvedAt,
                ])->save();
            }
        }
    }

    /**
     * @param  Collection<int, Certification>  $certifications
     */
    private function seedFixedStudentThreads(Collection $certifications): void
    {
        $student = User::query()->where('email', 'student@certify-lms.test')->first();
        $certification = $certifications->first();

        if ($student === null || $certification === null) {
            return;
        }

        $unresolvedCreatedAt = Carbon::now()->subDays(2);
        $unresolved = QaThread::create([
            'certification_id' => $certification->id,
            'user_id' => $student->id,
            'title' => '模試の合格ラインについて教えてください',
            'body' => '模試の合格判定ラインがどのくらいか気になっています。過去の受験者の平均点なども教えていただけると嬉しいです。',
            'status' => QaThreadStatus::Unresolved,
        ]);
        $unresolved->forceFill(['created_at' => $unresolvedCreatedAt, 'updated_at' => $unresolvedCreatedAt])->save();

        $resolvedCreatedAt = Carbon::now()->subDays(6);
        $resolved = QaThread::create([
            'certification_id' => $certification->id,
            'user_id' => $student->id,
            'title' => '演習問題の解説で分からない部分があります',
            'body' => '章末問題の解説3番の考え方がよく分からず、質問させてください。',
            'status' => QaThreadStatus::Unresolved,
        ]);
        $resolved->forceFill(['created_at' => $resolvedCreatedAt, 'updated_at' => $resolvedCreatedAt])->save();

        $coach = $certification->coaches->first();
        $replyAt = $resolvedCreatedAt->copy()->addHours(3);
        if ($coach !== null) {
            $reply = QaReply::create([
                'qa_thread_id' => $resolved->id,
                'user_id' => $coach->id,
                'body' => 'その問題は選択肢の消去法で考えると分かりやすいです。まず条件を1つずつ確認してみましょう。',
            ]);
            $reply->forceFill(['created_at' => $replyAt, 'updated_at' => $replyAt])->save();
        }

        $resolvedAt = $replyAt->copy()->addHour();
        $resolved->forceFill([
            'status' => QaThreadStatus::Resolved,
            'resolved_at' => $resolvedAt,
            'updated_at' => $resolvedAt,
        ])->save();
    }
}

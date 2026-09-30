<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 元々の`meetings`テーブルは(coach_id, scheduled_at) UNIQUEで同コーチ×同時刻の二重予約を
 * DBレベルで禁止する設計だったが、そのUNIQUE制約自体の追加が漏れていた(B-A-01)。
 * `MeetingController::store()`側のUniqueConstraintViolationException→409変換ロジックは
 * 既に実装済みのため、この制約を追加するだけで意図した設計が成立する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->unique(['coach_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropUnique(['coach_id', 'scheduled_at']);
        });
    }
};

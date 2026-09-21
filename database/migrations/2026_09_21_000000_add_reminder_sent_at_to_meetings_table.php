<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 面談リマインダー(前日・1時間前)の二重配信防止用フラグ。
 *
 * `notifications:send-meeting-reminders` コマンドが重複起動・再実行されても、各ウィンドウごとに
 * 一度だけ配信されたことを記録し、既送信の面談を再度対象にしないための送信済み時刻を保持する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->timestamp('eve_reminder_sent_at')->nullable()->after('completed_at');
            $table->timestamp('one_hour_reminder_sent_at')->nullable()->after('eve_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['eve_reminder_sent_at', 'one_hour_reminder_sent_at']);
        });
    }
};

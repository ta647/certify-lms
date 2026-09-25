<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 連携済コーチの面談が成立した際にGoogleカレンダーへ作成した予定のイベントIDを保持する。
 * キャンセル時にこのIDを使って連動削除する。未連携コーチの面談はnullのまま。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('google_calendar_event_id')->nullable()->after('meeting_url_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('google_calendar_event_id');
        });
    }
};

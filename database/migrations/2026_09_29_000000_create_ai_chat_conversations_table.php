<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI相談(Gemini)の会話単位テーブル。
 *
 * 教材Sectionから開始した会話は section_id / enrollment_id を持つ(資格文脈の解決に使う)。
 * 全般相談は両方null。教材由来でない会話は資格を保存せず、送信の都度その時点のデフォルト資格を
 * 解決する運用のため、enrollment_id は「教材由来のときだけ」設定する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignUlid('enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();
            $table->string('title', 100);
            $table->boolean('auto_titled')->default(false);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            // ウィジェット経由の「同じ受講生×同じSectionの会話を再利用する」検索を高速化
            $table->index(['user_id', 'section_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_conversations');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI相談(Gemini)のメッセージ1件分。user/assistant両方をこのテーブルに保持する。
 *
 * user_id は ai_chat_conversations.user_id の非正規化(1日あたりの送信件数を集計する
 * クエリを、会話テーブルへのJOIN無しで高速に行うため)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('ai_chat_conversation_id')->constrained('ai_chat_conversations')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20);
            $table->string('status', 20);
            $table->text('content');
            $table->text('error_detail')->nullable();
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->timestamps();

            // 日次送信件数の上限チェックを高速化
            $table->index(['user_id', 'role', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
    }
};

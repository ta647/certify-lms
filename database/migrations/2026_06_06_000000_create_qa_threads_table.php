<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 質問掲示板(qa-board)の質問スレッド。資格ごとに公開され、投稿者(受講生)本人のみが編集 / 削除 / 解決状態を変更できる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_threads', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('certification_id')
                ->constrained('certifications')
                ->restrictOnDelete();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->string('status')->default('unresolved');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['certification_id', 'status']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_threads');
    }
};

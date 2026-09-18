<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 管理者お知らせ配信の履歴。作成=即配信で、状態カラム・編集・取消の概念を持たない(不可逆)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title', 200);
            $table->text('body');
            $table->string('target_type', 20);
            $table->foreignUlid('target_certification_id')->nullable()
                ->constrained('certifications')->nullOnDelete();
            $table->foreignUlid('target_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->unsignedInteger('dispatched_count')->default(0);
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignUlid('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('dispatched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};

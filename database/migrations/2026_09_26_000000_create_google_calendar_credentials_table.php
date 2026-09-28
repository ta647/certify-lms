<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * コーチのGoogleカレンダー連携情報(OAuthトークン)。1コーチあたり最大1行(UNIQUE)、
 * 未連携は行なしで表現する。再連携はUPDATEで扱う(LearningHourTargetと同じ1:1パターン)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendar_credentials', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('calendar_id')->default('primary');
            $table->timestamp('connected_at');
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_credentials');
    }
};

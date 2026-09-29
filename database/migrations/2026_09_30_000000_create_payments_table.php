<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 追加面談パックの購入記録(Stripe決済)。
 *
 * quantity/amountは購入時点のMeetingPackの値のスナップショット(後からマスタを変更しても
 * 過去の購入を監査できるようにするため)。statusはpending(Checkout Session作成直後)→
 * succeeded/failedにWebhookで遷移する。pendingのまま作成するのは、決済完了前の「保留中」状態を
 * 履歴に残す(誤操作・未決済の証跡)ため。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('meeting_pack_id')->constrained('meeting_packs')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('amount');
            $table->string('status', 20);
            $table->string('stripe_checkout_session_id')->unique();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 追加面談購入テーブル(payments)が存在しない環境では FK 追加をスキップする。
        // 本Featureが後続で導入された環境でmigrate:freshを走らせれば、
        // paymentsテーブル作成後にこのMigrationが走りFK制約を追加できる。
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('meeting_quota_transactions', function (Blueprint $table) {
            $table->foreign('related_payment_id')
                ->references('id')
                ->on('payments')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('meeting_quota_transactions', function (Blueprint $table) {
            $table->dropForeign(['related_payment_id']);
        });
    }
};

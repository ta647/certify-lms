<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\QaReply;
use App\Models\QaThread;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * 質問掲示板のスレッドに新しい回答が投稿された際に発火するイベント。
 * 通知リスナー(SendQaReplyNotification)がスレッド投稿者への通知発火に利用する。
 */
final class QaReplyPosted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly QaThread $thread,
        public readonly QaReply $reply,
    ) {}
}

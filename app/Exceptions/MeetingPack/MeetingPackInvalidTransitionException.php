<?php

declare(strict_types=1);

namespace App\Exceptions\MeetingPack;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 許可されていない状態遷移(下書き→公開中→アーカイブ→下書きの一方向サイクル以外)を
 * 実行しようとした際の例外(HTTP 409)。`App\UseCases\MeetingPack\{Publish,Archive,Unarchive}Action` から throw する。
 */
final class MeetingPackInvalidTransitionException extends ConflictHttpException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forPublish(): self
    {
        return new self('下書き状態の面談パックのみ公開できます。');
    }

    public static function forArchive(): self
    {
        return new self('公開中の面談パックのみアーカイブできます。');
    }

    public static function forUnarchive(): self
    {
        return new self('アーカイブ済みの面談パックのみアーカイブ解除できます。');
    }
}

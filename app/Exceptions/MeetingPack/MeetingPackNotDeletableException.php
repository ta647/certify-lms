<?php

declare(strict_types=1);

namespace App\Exceptions\MeetingPack;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 削除条件を満たさない面談パックを削除しようとした際の例外(HTTP 409)。
 * `MeetingPack\DestroyAction` が「公開中は削除不可」のドメインルールから throw する。
 */
final class MeetingPackNotDeletableException extends ConflictHttpException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forPublished(): self
    {
        return new self('公開中の面談パックは削除できません。');
    }
}

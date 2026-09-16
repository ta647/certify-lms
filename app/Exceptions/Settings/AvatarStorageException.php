<?php

declare(strict_types=1);

namespace App\Exceptions\Settings;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * アバター画像の保存(アップロード)に失敗した際の例外(HTTP 500)。
 * `App\UseCases\Settings\StoreAvatarAction` がストレージ書き込み失敗時に throw する。
 */
class AvatarStorageException extends HttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(500, 'アイコン画像の保存に失敗しました。時間をおいて再度お試しください。', $previous);
    }
}

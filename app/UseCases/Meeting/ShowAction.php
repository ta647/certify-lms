<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Meeting;

/**
 * 面談詳細表示に必要な関連を読み込むユースケース。閲覧可否の判定(Policy)は Controller 側で行う。
 */
final class ShowAction
{
    public function __invoke(Meeting $meeting): Meeting
    {
        return $meeting->loadMissing([
            'enrollment.certification',
            'coach',
            'student',
            'canceledBy',
            'meetingMemo',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\User;

/**
 * 修了証(Certificate)のダウンロード可否を判定するPolicy。
 *
 * - student: 本人の修了証のみ(学習中でない=修了・退会前でもダウンロード可、修了証は永続資産のため)
 * - coach: 担当資格の修了証のみ
 * - admin: 全件
 */
class CertificatePolicy
{
    public function download(User $user, Certificate $certificate): bool
    {
        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Student => $certificate->user_id === $user->id,
            UserRole::Coach => in_array($certificate->certification_id, $user->coachingCertificationIds(), true),
        };
    }
}

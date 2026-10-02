<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | 管理者ダッシュボード集計のキャッシュ(T-A-06)
    |--------------------------------------------------------------------------
    |
    | 管理者ダッシュボードの全体 KPI(学習中/合格/不合格件数)と資格別修了率は、全 Enrollment を
    | 走査する重い集計のため一定時間キャッシュする(App\Services\EnrollmentStatsService)。
    |
    | 受講状態の遷移(合格・不合格・新規受講登録等)が起きると、EnrollmentStatusChangeService::
    | recordStatusChange() を通じて両キーとも即時無効化される。状態遷移を伴わない変化(資格の
    | 公開・非公開切替等)は TTL 失効まで反映されない(スコープ外として許容)。
    |
    */

    'admin_kpi_cache_key' => 'dashboard:admin:kpi',

    'admin_completion_rate_cache_key' => 'dashboard:admin:completion_rate',

    'admin_cache_ttl' => (int) env('ADMIN_DASHBOARD_CACHE_TTL', 300),

];

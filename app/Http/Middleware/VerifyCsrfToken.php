<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Stripe Webhook: 認証・セッションを持たない外部サーバからのPOSTのため、署名検証のみで正当性を担保する
        'webhooks/stripe',
    ];
}

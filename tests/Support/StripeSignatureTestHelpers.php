<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Stripe Webhookの`Stripe-Signature`ヘッダを、Stripe SDK本体(`Stripe\WebhookSignature`)と
 * 同一のアルゴリズム(HMAC-SHA256 of "{timestamp}.{payload}")で生成するテスト用ヘルパー。
 *
 * `StripeCheckoutService::verifyWebhookSignature()`をモックせず、実際の署名検証ロジックを
 * 通すテストで使う(正規署名 / 不正署名 / 期限切れタイムスタンプの検証)。
 */
trait StripeSignatureTestHelpers
{
    protected function generateStripeSignatureHeader(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return "t={$timestamp},v1={$signature}";
    }
}

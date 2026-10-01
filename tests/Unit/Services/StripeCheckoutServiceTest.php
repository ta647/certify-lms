<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\StripeCheckoutService;
use PHPUnit\Framework\Attributes\Group;
use Stripe\Exception\SignatureVerificationException;
use Tests\Support\StripeSignatureTestHelpers;
use Tests\TestCase;

/**
 * StripeCheckoutService::verifyWebhookSignature() を実際のStripe署名検証ロジック(Stripe SDK)に
 * 通して検証する。Controller層のテスト(StripeWebhookControllerTest)はこのメソッド自体をモックして
 * イベント種別ごとの処理を検証しているため、ここでは署名検証そのものの正規/不正/欠落系を扱う。
 */
#[Group('external-api')]
class StripeCheckoutServiceTest extends TestCase
{
    use StripeSignatureTestHelpers;

    private const WEBHOOK_SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    public function test_valid_signature_returns_event(): void
    {
        $payload = json_encode(['id' => 'evt_test_123', 'type' => 'checkout.session.completed', 'data' => ['object' => []]]);
        $signature = $this->generateStripeSignatureHeader($payload, self::WEBHOOK_SECRET);

        $event = app(StripeCheckoutService::class)->verifyWebhookSignature($payload, $signature);

        $this->assertSame('evt_test_123', $event->id);
        $this->assertSame('checkout.session.completed', $event->type);
    }

    public function test_signature_computed_with_wrong_secret_is_rejected(): void
    {
        $payload = json_encode(['id' => 'evt_test_123', 'type' => 'checkout.session.completed']);
        $signature = $this->generateStripeSignatureHeader($payload, 'whsec_different_secret');

        $this->expectException(SignatureVerificationException::class);

        app(StripeCheckoutService::class)->verifyWebhookSignature($payload, $signature);
    }

    public function test_signature_does_not_match_tampered_payload(): void
    {
        $originalPayload = json_encode(['id' => 'evt_test_123', 'amount' => 1000]);
        $signature = $this->generateStripeSignatureHeader($originalPayload, self::WEBHOOK_SECRET);
        $tamperedPayload = json_encode(['id' => 'evt_test_123', 'amount' => 999999]);

        $this->expectException(SignatureVerificationException::class);

        app(StripeCheckoutService::class)->verifyWebhookSignature($tamperedPayload, $signature);
    }

    public function test_missing_signature_header_is_rejected(): void
    {
        $payload = json_encode(['id' => 'evt_test_123']);

        $this->expectException(SignatureVerificationException::class);

        app(StripeCheckoutService::class)->verifyWebhookSignature($payload, '');
    }

    public function test_expired_timestamp_outside_tolerance_is_rejected(): void
    {
        $payload = json_encode(['id' => 'evt_test_123']);
        // Stripeのデフォルト許容幅(5分 = 300秒)を超えた古いタイムスタンプ
        $signature = $this->generateStripeSignatureHeader($payload, self::WEBHOOK_SECRET, time() - 400);

        $this->expectException(SignatureVerificationException::class);

        app(StripeCheckoutService::class)->verifyWebhookSignature($payload, $signature);
    }
}

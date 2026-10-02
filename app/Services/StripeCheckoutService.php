<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MeetingPack;
use App\Models\Payment;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Stripeとのやり取りを一手に引き受けるService。
 *
 * 他コードからはこのService経由でのみStripe SDKに触れる(=テスト時にモックする唯一の境界にする)。
 * S-A-01のGoogleCalendarService・S-A-02のGeminiClientと同じ方針。
 */
class StripeCheckoutService
{
    public function __construct(private readonly ?StripeClient $client = null) {}

    /**
     * 追加面談パック購入用のCheckout Sessionを作成する。
     * JPYはStripeのゼロdecimal通貨のため、円額をそのままunit_amountに渡す(セント変換不要)。
     */
    public function createCheckoutSession(
        MeetingPack $pack,
        Payment $payment,
        string $successUrl,
        string $cancelUrl,
    ): Session {
        return $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'client_reference_id' => $payment->id,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'jpy',
                    'unit_amount' => $pack->price,
                    'product_data' => [
                        'name' => $pack->name,
                    ],
                ],
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);
    }

    /**
     * Webhookのペイロードを署名検証してEventに変換する。
     *
     * @throws SignatureVerificationException 署名が不正な場合
     */
    public function verifyWebhookSignature(string $payload, string $signature): Event
    {
        return Webhook::constructEvent($payload, $signature, (string) config('services.stripe.webhook_secret'));
    }

    private function client(): StripeClient
    {
        return $this->client ?? new StripeClient((string) config('services.stripe.secret'));
    }
}

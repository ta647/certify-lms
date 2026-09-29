<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\StripeCheckoutService;
use App\UseCases\Payment\CompleteCheckoutAction;
use App\UseCases\Payment\ExpireCheckoutAction;
use App\UseCases\Payment\RefundPaymentAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;

/**
 * Stripeからの決済結果通知を受け取る認証不要の公開エンドポイント。
 * 正当性はStripeの署名検証のみで担保する(署名不正は400)。
 * 想定外のイベント種別は無視して200を返す(処理を破綻させない)。
 */
class StripeWebhookController extends Controller
{
    public function handle(
        Request $request,
        StripeCheckoutService $stripe,
        CompleteCheckoutAction $complete,
        ExpireCheckoutAction $expire,
        RefundPaymentAction $refund,
    ): JsonResponse {
        try {
            $event = $stripe->verifyWebhookSignature(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
            );
        } catch (SignatureVerificationException) {
            return response()->json(['error' => 'invalid signature'], 400);
        }

        match ($event->type) {
            'checkout.session.completed' => $complete(
                (string) $event->data->object->client_reference_id,
                $event->data->object->payment_intent,
            ),
            'checkout.session.expired' => $expire((string) $event->data->object->client_reference_id),
            'charge.refunded' => $refund((string) $event->data->object->payment_intent),
            default => null,
        };

        return response()->json(['received' => true]);
    }
}

<?php

declare(strict_types=1);

namespace App\UseCases\Payment;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use App\Services\StripeCheckoutService;
use Illuminate\Support\Facades\DB;

/**
 * 追加面談パック購入の開始ユースケース。
 *
 * Paymentをpendingで作成してからStripe Checkout Sessionを作成する(決済完了前の「保留中」状態を
 * 履歴に残すため)。quantity/amountは購入時点のMeetingPackの値をスナップショットする。
 */
final class StartCheckoutAction
{
    public function __construct(private readonly StripeCheckoutService $stripe) {}

    public function __invoke(User $student, MeetingPack $pack, string $successUrl, string $cancelUrl): string
    {
        $payment = DB::transaction(fn () => Payment::create([
            'user_id' => $student->id,
            'meeting_pack_id' => $pack->id,
            'quantity' => $pack->meeting_count,
            'amount' => $pack->price,
            'status' => PaymentStatus::Pending->value,
        ]));

        $session = $this->stripe->createCheckoutSession($pack, $payment, $successUrl, $cancelUrl);

        $payment->update(['stripe_checkout_session_id' => $session->id]);

        return $session->url;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Payment\CheckoutRequest;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\UseCases\Payment\StartCheckoutAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 追加面談パックの購入(Stripe Checkout)を扱うController。
 */
class MeetingQuotaCheckoutController extends Controller
{
    public function select(): View
    {
        return view('meeting-quota.checkout-select', [
            'plans' => MeetingPack::published()->ordered()->get(),
        ]);
    }

    public function create(CheckoutRequest $request, StartCheckoutAction $action): RedirectResponse
    {
        $pack = MeetingPack::published()->findOrFail($request->validated('meeting_pack_id'));

        $checkoutUrl = $action(
            $request->user(),
            $pack,
            route('meeting-quota.success').'?session_id={CHECKOUT_SESSION_ID}',
            route('meeting-quota.checkout.select'),
        );

        return redirect()->away($checkoutUrl);
    }

    public function success(Request $request): View
    {
        $sessionId = $request->query('session_id');

        $payment = is_string($sessionId)
            ? Payment::query()
                ->where('stripe_checkout_session_id', $sessionId)
                ->where('user_id', $request->user()->id)
                ->first()
            : null;

        return view('meeting-quota.success', ['payment' => $payment]);
    }
}

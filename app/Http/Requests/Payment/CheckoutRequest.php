<?php

declare(strict_types=1);

namespace App\Http\Requests\Payment;

use App\Enums\MeetingPackStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 追加面談パック購入の申込リクエスト。
 * 公開中(published)のパックのみ購入対象にできる(URL直指定での非公開パック購入を拒否)。
 */
class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'meeting_pack_id' => [
                'required',
                'ulid',
                Rule::exists('meeting_packs', 'id')->where('status', MeetingPackStatus::Published->value),
            ],
        ];
    }
}

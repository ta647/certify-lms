<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GoogleCalendarCredentialFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * コーチ単位のGoogleカレンダー連携情報(OAuthトークン)。1コーチあたり最大1行(UNIQUE)。
 *
 * 関連: User(親、UNIQUEで1:1)
 */
class GoogleCalendarCredential extends Model
{
    /** @use HasFactory<GoogleCalendarCredentialFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'calendar_id',
        'connected_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QaThreadStatus;
use Database\Factories\QaThreadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 質問掲示板(qa-board)の質問スレッド。
 *
 * 関連: Certification / User(投稿者) / QaReply
 * scope: visibleTo(User)(公開中資格 + student=無条件 / coach=担当資格のみ) / filter(array)
 */
class QaThread extends Model
{
    /** @use HasFactory<QaThreadFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'certification_id',
        'user_id',
        'title',
        'body',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'status' => QaThreadStatus::class,
        'resolved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Certification, $this>
     */
    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<QaReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(QaReply::class);
    }

    /**
     * 公開中資格のスレッドに限定した上で、student は無条件 / coach は担当資格のみに絞り込む scope。
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('certification', function (Builder $q) use ($user): void {
            $q->published();

            if ($user->role === \App\Enums\UserRole::Coach) {
                $q->assignedTo($user);
            }
        });
    }

    /**
     * @param array{status?: ?string, certification_id?: ?string, keyword?: ?string} $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['certification_id'])) {
            $query->where('certification_id', $filters['certification_id']);
        }

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function (Builder $q) use ($keyword): void {
                $q->where('title', 'LIKE', '%'.$keyword.'%')
                    ->orWhere('body', 'LIKE', '%'.$keyword.'%');
            });
        }

        return $query;
    }
}

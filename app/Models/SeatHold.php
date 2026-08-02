<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatHold extends Model
{
    use HasFactory;

    public const STATUS_LOCKED = 'locked';
    public const STATUS_BOOKED = 'booked';

    protected $fillable = [
        'seat_id',
        'schedule_id',
        'user_id',
        'booking_id',
        'lock_token',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Seat, $this>
     */
    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    /**
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isActive(): bool
    {
        if ($this->status === self::STATUS_BOOKED) {
            return true;
        }

        return $this->expires_at !== null && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return ! $this->isActive();
    }

    /**
     * Lock sementara yang sudah kedaluwarsa dan boleh diambil alih.
     *
     * @param  Builder<SeatHold>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where('status', self::STATUS_LOCKED)
            ->where('expires_at', '<=', now());
    }
}

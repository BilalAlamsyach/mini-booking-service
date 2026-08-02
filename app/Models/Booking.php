<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'booking_code',
        'user_id',
        'schedule_id',
        'status',
        'total_price',
        'passenger_name',
        'passenger_phone',
    ];

    protected function casts(): array
    {
        return [
            'total_price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * @return BelongsToMany<Seat, $this>
     */
    public function seats(): BelongsToMany
    {
        return $this->belongsToMany(Seat::class, 'booking_seats')
            ->withPivot('price')
            ->withTimestamps();
    }

    /**
     * @return HasMany<SeatHold, $this>
     */
    public function seatHolds(): HasMany
    {
        return $this->hasMany(SeatHold::class);
    }

    public static function generateCode(): string
    {
        return 'MBS-'.strtoupper(bin2hex(random_bytes(4)));
    }
}

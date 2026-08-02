<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Seat extends Model
{
    use HasFactory;

    protected $fillable = ['schedule_id', 'seat_number', 'seat_class'];

    /**
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * Relasi hasOne, bukan hasMany, karena `seat_holds.seat_id` unik.
     *
     * @return HasOne<SeatHold, $this>
     */
    public function hold(): HasOne
    {
        return $this->hasOne(SeatHold::class);
    }
}

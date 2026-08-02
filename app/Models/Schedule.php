<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'vehicle_code',
        'departure_date',
        'departure_time',
        'arrival_time',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Route, $this>
     */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    /**
     * @return HasMany<Seat, $this>
     */
    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    /**
     * @return HasMany<SeatHold, $this>
     */
    public function seatHolds(): HasMany
    {
        return $this->hasMany(SeatHold::class);
    }

    /**
     * @param  Builder<Schedule>  $query
     */
    public function scopeDepartingOn(Builder $query, string $date): void
    {
        $query->whereDate('departure_date', $date);
    }
}

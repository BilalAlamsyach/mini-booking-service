<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rute perjalanan milik sebuah operator.
 *
 * Catatan: nama kelas ini bertabrakan dengan facade
 * `Illuminate\Support\Facades\Route`. Berkas yang membutuhkan keduanya harus
 * memberi alias pada salah satunya; `routes/api.php` sengaja hanya memakai
 * facade dan tidak pernah meng-import model ini.
 */
class Route extends Model
{
    use HasFactory;

    protected $fillable = ['operator_id', 'origin', 'destination', 'duration_minutes'];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Operator, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /**
     * @return HasMany<Schedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}

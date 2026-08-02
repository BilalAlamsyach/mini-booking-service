<?php

namespace App\Console\Commands;

use App\Models\SeatHold;
use Illuminate\Console\Command;

/**
 * (e) Lepaskan lock yang sudah kedaluwarsa.
 *
 * Perintah ini bersifat housekeeping, bukan penentu kebenaran. Setiap
 * pembacaan ketersediaan, percobaan lock, dan konfirmasi booking sudah
 * mengevaluasi `expires_at` sendiri (lazy expiry), sehingga kursi dengan lock
 * kedaluwarsa tetap bisa dipesan orang lain walaupun scheduler tidak berjalan.
 * Tugas perintah ini hanya membuang baris basi agar tabel tetap ramping.
 */
class ReleaseExpiredSeatLocks extends Command
{
    protected $signature = 'seats:release-expired';

    protected $description = 'Hapus seat lock sementara yang sudah melewati waktu kedaluwarsa';

    public function handle(): int
    {
        // Scope `expired()` hanya menyentuh hold berstatus `locked`; hold
        // `booked` tidak punya expires_at dan tidak boleh ikut terhapus.
        $released = SeatHold::query()->expired()->delete();

        $this->info($released === 0
            ? 'Tidak ada lock kedaluwarsa.'
            : "{$released} lock kedaluwarsa dilepas.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Services;

use App\Exceptions\LockExpiredException;
use App\Exceptions\LockNotFoundException;
use App\Exceptions\SeatUnavailableException;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\SeatHold;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BookingService
{
    private const TRANSACTION_ATTEMPTS = 3;

    /**
     * Ubah lock sementara menjadi booking permanen.
     *
     * Seluruh pemeriksaan dilakukan ulang di dalam transaksi dengan
     * `lockForUpdate()`. Hasil pengecekan pada endpoint ketersediaan sebelumnya
     * tidak dipercaya, karena keadaan bisa berubah di antara dua request.
     *
     * @param  array{passenger_name: string, passenger_phone: string}  $passenger
     *
     * @throws LockNotFoundException|LockExpiredException|SeatUnavailableException
     */
    public function confirm(User $user, string $lockToken, array $passenger): Booking
    {
        return DB::transaction(function () use ($user, $lockToken, $passenger) {
            $holds = SeatHold::query()
                ->where('lock_token', $lockToken)
                ->orderBy('seat_id')
                ->lockForUpdate()
                ->get();

            // Token tidak dikenal dan token milik orang lain dijawab sama,
            // supaya keberadaan token orang lain tidak bocor.
            if ($holds->isEmpty() || $holds->contains(fn (SeatHold $hold) => $hold->user_id !== $user->id)) {
                throw LockNotFoundException::make();
            }

            $this->assertHoldsStillValid($holds);

            $schedule = Schedule::query()
                ->whereKey($holds->first()->schedule_id)
                ->lockForUpdate()
                ->firstOrFail();

            $booking = Booking::create([
                'booking_code' => Booking::generateCode(),
                'user_id' => $user->id,
                'schedule_id' => $schedule->id,
                'status' => Booking::STATUS_CONFIRMED,
                'total_price' => $schedule->price * $holds->count(),
                'passenger_name' => $passenger['passenger_name'],
                'passenger_phone' => $passenger['passenger_phone'],
            ]);

            foreach ($holds as $hold) {
                $booking->seats()->attach($hold->seat_id, ['price' => $schedule->price]);

                // Hold berubah permanen: tidak lagi punya waktu kedaluwarsa,
                // sehingga kursi tetap terkunci selamanya untuk booking ini.
                $hold->forceFill([
                    'status' => SeatHold::STATUS_BOOKED,
                    'booking_id' => $booking->id,
                    'expires_at' => null,
                ])->save();
            }

            return $booking->load(['seats', 'schedule.route.operator']);
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SeatHold>  $holds
     */
    private function assertHoldsStillValid($holds): void
    {
        $alreadyBooked = $holds
            ->filter(fn (SeatHold $hold) => $hold->status === SeatHold::STATUS_BOOKED)
            ->map(fn (SeatHold $hold) => $hold->seat->seat_number)
            ->values()
            ->all();

        if ($alreadyBooked !== []) {
            throw SeatUnavailableException::forSeats($alreadyBooked);
        }

        // Cukup satu kursi kedaluwarsa untuk membatalkan seluruh transaksi:
        // booking parsial akan membingungkan pengguna.
        if ($holds->contains(fn (SeatHold $hold) => $hold->isExpired())) {
            throw LockExpiredException::make();
        }
    }
}

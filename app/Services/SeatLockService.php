<?php

namespace App\Services;

use App\Exceptions\SeatUnavailableException;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;


class SeatLockService
{

    private const TRANSACTION_ATTEMPTS = 3;

    /**
     *
     * @param  list<int>  $seatIds
     * @return array{lock_token: string, expires_at: Carbon, seats: Collection<int, Seat>}
     *
     * @throws SeatUnavailableException|ValidationException
     */
    
    public function lock(User $user, int $scheduleId, array $seatIds): array
    {
        $seatIds = $this->normalizeSeatIds($seatIds);

        return DB::transaction(function () use ($user, $scheduleId, $seatIds) {
            // Urutan id yang konsisten (ASC) pada setiap transaksi mencegah dua
            // request multi-kursi saling menunggu lock milik satu sama lain.
            $seats = Seat::query()
                ->whereIn('id', $seatIds)
                ->where('schedule_id', $scheduleId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($seats->count() !== count($seatIds)) {
                throw ValidationException::withMessages([
                    'seat_ids' => ['Sebagian kursi tidak ditemukan pada jadwal ini.'],
                ]);
            }

            $holds = SeatHold::query()
                ->whereIn('seat_id', $seatIds)
                ->orderBy('seat_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('seat_id');

            $this->assertSeatsAreClaimable($user, $seats, $holds);

            $lockToken = (string) Str::uuid();
            $expiresAt = $this->expiryFromNow();

            // Lepas lock lama milik pengguna ini pada jadwal yang sama supaya
            // satu pengguna hanya menahan satu set kursi per jadwal.
            $this->releaseOtherLocksOfUser($user, $scheduleId, $seatIds);

            foreach ($seats as $seat) {
                $this->claimSeat($seat, $holds->get($seat->id), $user, $scheduleId, $lockToken, $expiresAt);
            }

            return [
                'lock_token' => $lockToken,
                'expires_at' => $expiresAt,
                'seats' => $seats,
            ];
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Lepas lock secara manual (mis. pengguna membatalkan pilihan kursi).
     *
     * @return int Jumlah kursi yang dibebaskan.
     */
    public function release(User $user, string $lockToken): int
    {
        return SeatHold::query()
            ->where('lock_token', $lockToken)
            ->where('user_id', $user->id)
            ->where('status', SeatHold::STATUS_LOCKED)
            ->delete();
    }

    /**
     * Peta ketersediaan seluruh kursi pada satu jadwal.
     *
     * Status yang mungkin: `available`, `locked` (oleh orang lain),
     * `locked_by_you`, dan `booked`.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function availability(int $scheduleId, ?int $userId = null): Collection
    {
        return Seat::query()
            ->where('schedule_id', $scheduleId)
            ->with('hold')
            ->orderBy('id')
            ->get()
            ->map(fn (Seat $seat) => [
                'id' => $seat->id,
                'seat_number' => $seat->seat_number,
                'seat_class' => $seat->seat_class,
                'status' => $this->resolveStatus($seat->hold, $userId),
                'locked_until' => $this->resolveLockedUntil($seat->hold),
            ]);
    }

    /**
     * Jumlah kursi yang masih bisa dipesan pada satu jadwal.
     */
    public function availableSeatCount(int $scheduleId): int
    {
        return Seat::query()
            ->where('schedule_id', $scheduleId)
            ->whereDoesntHave('hold', function ($query) {
                // Hold dianggap menahan kursi bila statusnya `booked`, atau
                // masih `locked` dan belum lewat waktu kedaluwarsa.
                $query->where('status', SeatHold::STATUS_BOOKED)
                    ->orWhere(function ($inner) {
                        $inner->where('status', SeatHold::STATUS_LOCKED)
                            ->where('expires_at', '>', now());
                    });
            })
            ->count();
    }

    public function expiryFromNow(): Carbon
    {
        return now()->addSeconds((int) config('booking.lock_ttl_seconds'));
    }

    /* ------------------------------------------------------------------ */
    /* Helper internal                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * @param  list<int>  $seatIds
     * @return list<int>
     */
    private function normalizeSeatIds(array $seatIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $seatIds)));
        sort($ids);

        $max = (int) config('booking.max_seats_per_booking');

        if ($ids === [] || count($ids) > $max) {
            throw ValidationException::withMessages([
                'seat_ids' => ["Pilih antara 1 sampai {$max} kursi."],
            ]);
        }

        return $ids;
    }

    /**
     * @param  Collection<int, Seat>  $seats
     * @param  Collection<int, SeatHold>  $holds
     *
     * @throws SeatUnavailableException
     */
    private function assertSeatsAreClaimable(User $user, Collection $seats, Collection $holds): void
    {
        $conflicts = [];

        foreach ($seats as $seat) {
            $hold = $holds->get($seat->id);

            if ($hold === null) {
                continue;
            }

            // Kursi yang sudah dipesan tidak bisa dikunci lagi, bahkan oleh
            // pemesannya sendiri.
            if ($hold->status === SeatHold::STATUS_BOOKED) {
                $conflicts[] = $seat->seat_number;

                continue;
            }

            // Lock aktif milik orang lain. Lock kedaluwarsa dilewati di sini,
            // artinya boleh diambil alih.
            if ($hold->isActive() && $hold->user_id !== $user->id) {
                $conflicts[] = $seat->seat_number;
            }
        }

        if ($conflicts !== []) {
            throw SeatUnavailableException::forSeats($conflicts);
        }
    }

    /**
     * Buat baris hold baru, atau ambil alih baris yang sudah ada (milik sendiri
     * maupun milik orang lain yang sudah kedaluwarsa).
     */
    private function claimSeat(
        Seat $seat,
        ?SeatHold $existing,
        User $user,
        int $scheduleId,
        string $lockToken,
        Carbon $expiresAt
    ): void {
        $attributes = [
            'schedule_id' => $scheduleId,
            'user_id' => $user->id,
            'booking_id' => null,
            'lock_token' => $lockToken,
            'status' => SeatHold::STATUS_LOCKED,
            'expires_at' => $expiresAt,
        ];

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();

            return;
        }

        try {
            SeatHold::create($attributes + ['seat_id' => $seat->id]);
        } catch (UniqueConstraintViolationException|QueryException $e) {
            // Lapis pertahanan kedua: request lain menyisipkan hold untuk kursi
            // yang sama sebelum transaksi ini sempat commit. Unique index yang
            // menolaknya, bukan logika aplikasi.
            if ($e instanceof UniqueConstraintViolationException || $this->isDuplicateKey($e)) {
                throw SeatUnavailableException::forSeats([$seat->seat_number]);
            }

            throw $e;
        }
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? null) === '23000';
    }

    /**
     * @param  list<int>  $keepSeatIds
     */
    private function releaseOtherLocksOfUser(User $user, int $scheduleId, array $keepSeatIds): void
    {
        SeatHold::query()
            ->where('user_id', $user->id)
            ->where('schedule_id', $scheduleId)
            ->where('status', SeatHold::STATUS_LOCKED)
            ->whereNotIn('seat_id', $keepSeatIds)
            ->delete();
    }

    private function resolveStatus(?SeatHold $hold, ?int $userId): string
    {
        if ($hold === null) {
            return 'available';
        }

        if ($hold->status === SeatHold::STATUS_BOOKED) {
            return 'booked';
        }

        if ($hold->isExpired()) {
            return 'available';
        }

        return $hold->user_id === $userId ? 'locked_by_you' : 'locked';
    }

    private function resolveLockedUntil(?SeatHold $hold): ?string
    {
        if ($hold === null || $hold->status === SeatHold::STATUS_BOOKED || $hold->isExpired()) {
            return null;
        }

        return $hold->expires_at->toIso8601String();
    }
}

<?php

namespace App\Console\Commands;

use App\Exceptions\BookingException;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\User;
use App\Services\BookingService;
use App\Services\SeatLockService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Alat bantu pengujian manual: menyiapkan keadaan kursi sebelum membuka UI.
 *
 * Seluruh penguncian tetap melewati SeatLockService, jadi perintah ini tidak
 * bisa menciptakan keadaan yang mustahil dicapai lewat API biasa — kursi yang
 * sudah dipesan orang lain tetap ditolak.
 */
class DemoLockSeats extends Command
{
    protected $signature = 'seats:demo-lock
        {schedule? : ID jadwal. Kosongkan untuk melihat daftar jadwal terdekat}
        {seats?* : Nomor kursi (A1 B2) atau ID kursi. Kosongkan untuk melihat peta kursi}
        {--user=user2@example.com : Email pemilik lock}
        {--minutes=5 : Durasi lock dalam menit}
        {--booked : Jadikan pemesanan permanen, bukan lock sementara}
        {--release : Lepas semua lock milik user tersebut pada jadwal ini}';

    protected $description = 'Kunci kursi untuk keperluan uji coba manual (lihat kursi terkunci di UI)';

    public function handle(SeatLockService $seatLocks, BookingService $bookings): int
    {
        if (! $this->argument('schedule')) {
            return $this->listSchedules($seatLocks);
        }

        $schedule = Schedule::with('route.operator')->find($this->argument('schedule'));

        if (! $schedule) {
            $this->error('Jadwal tidak ditemukan. Jalankan tanpa argumen untuk melihat daftar jadwal.');

            return self::FAILURE;
        }

        $user = User::where('email', $this->option('user'))->first();

        if (! $user) {
            $this->error("User {$this->option('user')} tidak ditemukan.");
            $this->line('Akun seed: user@example.com, user2@example.com');

            return self::FAILURE;
        }

        if ($this->option('release')) {
            return $this->releaseLocks($schedule, $user, $seatLocks);
        }

        $seatArguments = $this->argument('seats');

        if ($seatArguments === []) {
            $this->showSeatMap($schedule, $seatLocks, $user);
            $this->newLine();
            $this->line('Tambahkan nomor kursi untuk menguncinya, contoh:');
            $this->line("  php artisan seats:demo-lock {$schedule->id} A1 A2 B3 --minutes=60");

            return self::SUCCESS;
        }

        return $this->lockSeats($schedule, $user, $seatArguments, $seatLocks, $bookings);
    }

    /* ------------------------------------------------------------------ */

    private function listSchedules(SeatLockService $seatLocks): int
    {
        $schedules = Schedule::with('route.operator')
            ->whereDate('departure_date', '>=', today())
            ->orderBy('departure_date')
            ->orderBy('departure_time')
            ->limit(12)
            ->get();

        if ($schedules->isEmpty()) {
            $this->warn('Belum ada jadwal. Jalankan: php artisan migrate --seed');

            return self::FAILURE;
        }

        $this->info('Jadwal terdekat:');

        $this->table(
            ['ID', 'Tanggal', 'Berangkat', 'Rute', 'Operator', 'Kursi bebas'],
            $schedules->map(fn (Schedule $schedule) => [
                $schedule->id,
                $schedule->departure_date->toDateString(),
                substr((string) $schedule->departure_time, 0, 5),
                $schedule->route->origin.' → '.$schedule->route->destination,
                $schedule->route->operator->name,
                $seatLocks->availableSeatCount($schedule->id).'/'.$schedule->seats()->count(),
            ])->all()
        );

        $this->newLine();
        $this->line('Lihat peta kursi sebuah jadwal:');
        $this->line('  php artisan seats:demo-lock '.$schedules->first()->id);

        return self::SUCCESS;
    }

    private function releaseLocks(Schedule $schedule, User $user, SeatLockService $seatLocks): int
    {
        $released = SeatHold::query()
            ->where('schedule_id', $schedule->id)
            ->where('user_id', $user->id)
            ->where('status', SeatHold::STATUS_LOCKED)
            ->delete();

        $this->info($released === 0
            ? "Tidak ada lock milik {$user->email} pada jadwal {$schedule->id}."
            : "{$released} lock milik {$user->email} dilepas.");

        $this->newLine();
        $this->showSeatMap($schedule, $seatLocks, $user);

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $seatArguments
     */
    private function lockSeats(
        Schedule $schedule,
        User $user,
        array $seatArguments,
        SeatLockService $seatLocks,
        BookingService $bookings
    ): int {
        $seatIds = $this->resolveSeatIds($schedule, $seatArguments);

        if ($seatIds === null) {
            return self::FAILURE;
        }

        $minutes = max(1, (int) $this->option('minutes'));
        config([
            'booking.lock_ttl_seconds' => $minutes * 60,
            'booking.max_seats_per_booking' => max(count($seatIds), (int) config('booking.max_seats_per_booking')),
        ]);

        try {
            $lock = $seatLocks->lock($user, $schedule->id, $seatIds);
        } catch (BookingException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $seatNumbers = $lock['seats']->pluck('seat_number')->implode(', ');

        if ($this->option('booked')) {
            $booking = $bookings->confirm($user, $lock['lock_token'], [
                'passenger_name' => $user->name,
                'passenger_phone' => '081200000000',
            ]);

            $this->info("Kursi {$seatNumbers} DIPESAN permanen oleh {$user->email}.");
            $this->line("Kode booking: {$booking->booking_code}");
        } else {
            $this->info("Kursi {$seatNumbers} dikunci oleh {$user->email} selama {$minutes} menit.");
            $this->line('Lock token : '.$lock['lock_token']);
            $this->line('Berlaku s/d: '.$lock['expires_at']->format('H:i:s'));
        }

        $this->newLine();
        $this->showSeatMap($schedule, $seatLocks, $user);

        $this->newLine();
        $this->line('Buka UI dan login sebagai akun LAIN untuk melihat kursi ini bertanda kuning:');
        $this->line('  '.($user->email === 'user2@example.com' ? 'user@example.com' : 'user2@example.com').' / password');

        return self::SUCCESS;
    }

    /**
     * Terima nomor kursi ("A1") maupun ID kursi ("64").
     *
     * @param  list<string>  $seatArguments
     * @return list<int>|null
     */
    private function resolveSeatIds(Schedule $schedule, array $seatArguments): ?array
    {
        $seats = Seat::where('schedule_id', $schedule->id)->get();
        $ids = [];
        $unknown = [];

        foreach ($seatArguments as $argument) {
            $match = $seats->first(fn (Seat $seat) => strcasecmp($seat->seat_number, $argument) === 0)
                ?? $seats->first(fn (Seat $seat) => (string) $seat->id === $argument);

            if ($match === null) {
                $unknown[] = $argument;

                continue;
            }

            $ids[] = $match->id;
        }

        if ($unknown !== []) {
            $this->error('Kursi tidak ditemukan pada jadwal ini: '.implode(', ', $unknown));
            $this->line('Nomor kursi tersedia: '.$seats->pluck('seat_number')->implode(', '));

            return null;
        }

        return array_values(array_unique($ids));
    }

    private function showSeatMap(Schedule $schedule, SeatLockService $seatLocks, User $user): void
    {
        $labels = [
            'available' => 'tersedia',
            'locked' => 'DIKUNCI orang lain',
            'locked_by_you' => 'DIKUNCI oleh user ini',
            'booked' => 'SUDAH DIPESAN',
        ];

        $rows = $seatLocks->availability($schedule->id, $user->id)
            ->reject(fn (array $seat) => $seat['status'] === 'available')
            ->map(fn (array $seat) => [
                $seat['seat_number'],
                $seat['seat_class'],
                $labels[$seat['status']],
                $seat['locked_until'] ? substr($seat['locked_until'], 11, 8) : '—',
            ])
            ->values();

        $available = $seatLocks->availableSeatCount($schedule->id);
        $total = $schedule->seats()->count();

        $this->line("Jadwal {$schedule->id} — kursi bebas: {$available}/{$total}");

        if ($rows->isEmpty()) {
            $this->line('Semua kursi masih tersedia.');

            return;
        }
        $this->table(['Kursi', 'Kelas', 'Status', 'Terkunci s/d'], $rows->all());
    }
}

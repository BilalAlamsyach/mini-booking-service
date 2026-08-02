<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;


class LoadTestSeatLock extends Command
{
    protected $signature = 'seats:load-test
        {--requests=50 : Jumlah request paralel}
        {--url=http://localhost/MiniBookingService/public : Base URL aplikasi}
        {--schedule= : ID jadwal. Default: jadwal dengan tanggal terjauh}
        {--seat= : Nomor atau ID kursi. Default: kursi bebas pertama}
        {--keep : Jangan bersihkan lock dan pengguna uji setelah selesai}';

    protected $description = 'Tembakkan N request paralel ke satu kursi untuk menguji penanganan race condition';

    private const USER_DOMAIN = '@loadtest.local';

    public function handle(): int
    {
        $total = max(2, (int) $this->option('requests'));
        $baseUrl = rtrim((string) $this->option('url'), '/');

        if (! $this->serverIsReachable($baseUrl)) {
            return self::FAILURE;
        }

        $seat = $this->resolveSeat();

        if (! $seat) {
            return self::FAILURE;
        }

        $this->line("Target  : kursi {$seat->seat_number} (id {$seat->id}) pada jadwal {$seat->schedule_id}");
        $this->line("Server  : {$baseUrl}");
        $this->line("Request : {$total} paralel, masing-masing dari pengguna berbeda");
        $this->newLine();

        $tokens = $this->prepareUsers($total);

        $this->line('Menembakkan request…');
        $results = $this->fireConcurrently($baseUrl, $tokens, $seat);

        $this->newLine();
        $this->reportResponses($results, $total);

        $verdict = $this->reportDatabase($seat, $results);

        if (! $this->option('keep')) {
            $this->cleanUp($seat);
        }

        return $verdict;
    }

    /* ------------------------------------------------------------------ */

    private function serverIsReachable(string $baseUrl): bool
    {
        $ch = curl_init($baseUrl.'/up');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200) {
            return true;
        }

        $this->error("Server tidak merespons di {$baseUrl} (HTTP {$code}).");
        $this->line('Jalankan aplikasi lewat Apache XAMPP, lalu ulangi. Contoh:');
        $this->line('  php artisan seats:load-test --url=http://localhost/MiniBookingService/public');

        return false;
    }

    private function resolveSeat(): ?Seat
    {
        $scheduleId = $this->option('schedule');

        if ($scheduleId) {
            $schedule = Schedule::find($scheduleId);

            if (! $schedule) {
                $this->error("Jadwal {$scheduleId} tidak ditemukan.");

                return null;
            }
        } else {
            // Jadwal terjauh dipilih supaya data demo di tanggal dekat tidak
            // ikut teraduk oleh pengujian.
            $schedule = Schedule::orderByDesc('departure_date')->orderByDesc('id')->first();

            if (! $schedule) {
                $this->error('Belum ada jadwal. Jalankan: php artisan migrate --seed');

                return null;
            }
        }

        $query = Seat::where('schedule_id', $schedule->id);

        if ($nomor = $this->option('seat')) {
            $seat = (clone $query)->where('seat_number', $nomor)->first()
                ?? (clone $query)->whereKey($nomor)->first();

            if (! $seat) {
                $this->error("Kursi {$nomor} tidak ada pada jadwal {$schedule->id}.");

                return null;
            }
        } else {
            $seat = (clone $query)->whereDoesntHave('hold')->orderBy('id')->first();

            if (! $seat) {
                $this->error("Tidak ada kursi bebas pada jadwal {$schedule->id}.");

                return null;
            }
        }

        // Kursi harus benar-benar kosong agar hasilnya bermakna.
        SeatHold::where('seat_id', $seat->id)->where('status', SeatHold::STATUS_LOCKED)->delete();

        if (SeatHold::where('seat_id', $seat->id)->exists()) {
            $this->error("Kursi {$seat->seat_number} sudah dipesan permanen. Pilih kursi lain dengan --seat=");

            return null;
        }

        return $seat;
    }

    /**
     * Siapkan N pengguna berbeda beserta tokennya.
     *
     * Harus pengguna berbeda: satu pengguna yang mengunci kursi miliknya
     * sendiri berulang kali memang dibolehkan sistem, jadi tidak akan
     * menghasilkan konflik apa pun.
     *
     * @return list<string>
     */
    private function prepareUsers(int $total): array
    {
        $this->line("Menyiapkan {$total} pengguna uji…");

        // Hash dihitung sekali lalu dipakai ulang; bcrypt 50 kali tidak perlu.
        $password = Hash::make('password');
        $now = now();
        $rows = [];

        for ($i = 1; $i <= $total; $i++) {
            $rows[] = [
                'name' => "Load Test {$i}",
                'email' => "loadtest{$i}".self::USER_DOMAIN,
                'password' => $password,
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        User::upsert($rows, ['email'], ['name']);

        $users = User::where('email', 'like', '%'.self::USER_DOMAIN)->limit($total)->get();
        $tokens = [];

        foreach ($users as $user) {
            $user->tokens()->delete();
            $tokens[] = $user->createToken('loadtest')->plainTextToken;
        }

        return $tokens;
    }

    /**
     * @param  list<string>  $tokens
     * @return array{codes: array<int, int>, elapsed_ms: float, bodies: array<int, string>}
     */
    private function fireConcurrently(string $baseUrl, array $tokens, Seat $seat): array
    {
        $payload = json_encode([
            'schedule_id' => $seat->schedule_id,
            'seat_ids' => [$seat->id],
        ]);

        $multi = curl_multi_init();
        $handles = [];

        // Seluruh handle didaftarkan lebih dulu, baru dijalankan sekaligus,
        // sehingga request berangkat sedekat mungkin secara bersamaan.
        foreach ($tokens as $token) {
            $ch = curl_init($baseUrl.'/api/seat-locks');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Bearer '.$token,
                ],
            ]);

            curl_multi_add_handle($multi, $ch);
            $handles[] = $ch;
        }

        $start = microtime(true);

        do {
            $status = curl_multi_exec($multi, $running);

            if ($running) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running && $status === CURLM_OK);

        $elapsed = (microtime(true) - $start) * 1000;

        $codes = [];
        $bodies = [];

        foreach ($handles as $ch) {
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $codes[$code] = ($codes[$code] ?? 0) + 1;

            if (! in_array($code, [201, 409], true)) {
                $bodies[$code] = substr((string) curl_multi_getcontent($ch), 0, 200);
            }

            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }

        curl_multi_close($multi);

        return ['codes' => $codes, 'elapsed_ms' => $elapsed, 'bodies' => $bodies];
    }

    private function reportResponses(array $results, int $total): void
    {
        $labels = [
            201 => 'berhasil mengunci',
            409 => 'ditolak — kursi sudah dikunci',
            401 => 'tidak terautentikasi',
            422 => 'validasi gagal',
            500 => 'kesalahan server',
            0 => 'gagal terhubung',
        ];

        $rows = [];

        foreach ($results['codes'] as $code => $count) {
            $rows[] = [$code, $labels[$code] ?? '—', $count];
        }

        usort($rows, fn ($a, $b) => $a[0] <=> $b[0]);

        $this->table(['HTTP', 'Keterangan', 'Jumlah'], $rows);

        $this->line(sprintf(
            'Selesai dalam %.0f ms  (rata-rata %.1f ms/request)',
            $results['elapsed_ms'],
            $results['elapsed_ms'] / $total
        ));

        foreach ($results['bodies'] as $code => $body) {
            $this->warn("Contoh respons {$code}: {$body}");
        }
    }

    private function reportDatabase(Seat $seat, array $results): int
    {
        $holds = SeatHold::with('user')->where('seat_id', $seat->id)->get();
        $sukses = $results['codes'][201] ?? 0;

        $this->newLine();
        $this->line('Verifikasi database:');
        $this->line(sprintf('  baris seat_holds untuk kursi ini : %d   (harus tepat 1)', $holds->count()));

        if ($holds->count() === 1) {
            $this->line('  pemenang                         : '.$holds->first()->user->email);
        }

        $this->newLine();

        if ($sukses === 1 && $holds->count() === 1) {
            $this->info('LULUS — tepat satu request berhasil mengunci kursi, sisanya ditolak.');

            return self::SUCCESS;
        }

        $this->error(sprintf(
            'GAGAL — %d request berhasil dan %d baris hold tercatat. Seharusnya masing-masing 1.',
            $sukses,
            $holds->count()
        ));

        return self::FAILURE;
    }

    private function cleanUp(Seat $seat): void
    {
        SeatHold::where('seat_id', $seat->id)->where('status', SeatHold::STATUS_LOCKED)->delete();

        // Menghapus pengguna uji sekaligus mencabut token dan hold miliknya
        // lewat foreign key cascade.
        $dihapus = User::where('email', 'like', '%'.self::USER_DOMAIN)->delete();

        $this->newLine();
        $this->line("Pembersihan: {$dihapus} pengguna uji dihapus, lock dilepas.");
        $this->line('Gunakan --keep bila ingin memeriksa sisa datanya sendiri.');
    }
}

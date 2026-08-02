<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Operator;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\SeatHold;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Waktu keberangkatan yang dibuat untuk setiap rute, setiap hari.
     *
     * @var list<string>
     */
    private const DEPARTURE_TIMES = ['07:00:00', '13:00:00', '20:00:00'];

    private const DAYS_AHEAD = 7;

    public function run(): void
    {
        $users = $this->seedUsers();
        $routes = $this->seedOperatorsAndRoutes();
        $this->seedSchedulesAndSeats($routes);
    }

    private function seedUsers(): array
    {
        return [
            'bilal' => User::updateOrCreate(
                ['email' => 'user@example.com'],
                [
                    'name' => 'Mochamad Bilal Alamsyach',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            ),
            'syifa' => User::updateOrCreate(
                ['email' => 'user2@example.com'],
                [
                    'name' => 'Syifa Syahida',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            ),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Route>
     */
    private function seedOperatorsAndRoutes()
    {
        $sinarJaya = Operator::updateOrCreate(
            ['code' => 'SJ01'],
            ['name' => 'Sinar Jaya Transport', 'phone' => '02155667788']
        );

        $rosalia = Operator::updateOrCreate(
            ['code' => 'RI02'],
            ['name' => 'Rosalia Indah', 'phone' => '02744112233']
        );

        $definitions = [
            [$sinarJaya, 'Jakarta', 'Yogyakarta', 480, 320000],
            [$sinarJaya, 'Jakarta', 'Bandung', 180, 120000],
            [$rosalia, 'Surabaya', 'Malang', 120, 90000],
            [$rosalia, 'Semarang', 'Solo', 150, 110000],
        ];

        return collect($definitions)->map(function (array $definition) {
            [$operator, $origin, $destination, $duration, $price] = $definition;

            $route = Route::updateOrCreate(
                [
                    'operator_id' => $operator->id,
                    'origin' => $origin,
                    'destination' => $destination,
                ],
                ['duration_minutes' => $duration]
            );

            // Harga disimpan di jadwal; dibawa lewat atribut sementara.
            $route->setAttribute('seed_price', $price);

            return $route;
        });
    }

    /**
     * Buat jadwal untuk 7 hari ke depan beserta 20 kursi per jadwal.
     *
     * @param  \Illuminate\Support\Collection<int, Route>  $routes
     */
    private function seedSchedulesAndSeats($routes): void
    {
        $seatNumbers = collect(range(1, 10))
            ->flatMap(fn (int $n) => ['A'.$n, 'B'.$n])
            ->all();

        $seatRows = [];

        foreach ($routes as $route) {
            for ($day = 0; $day < self::DAYS_AHEAD; $day++) {
                $date = Carbon::today()->addDays($day);

                foreach (self::DEPARTURE_TIMES as $index => $departureTime) {
                    $departsAt = Carbon::parse($date->toDateString().' '.$departureTime);

                    $schedule = Schedule::updateOrCreate(
                        [
                            'route_id' => $route->id,
                            'departure_date' => $date->toDateString(),
                            'departure_time' => $departureTime,
                        ],
                        [
                            'vehicle_code' => sprintf('%s-%02d', $route->operator->code, $index + 1),
                            'arrival_time' => $departsAt->copy()
                                ->addMinutes($route->duration_minutes)
                                ->format('H:i:s'),
                            'price' => $route->getAttribute('seed_price'),
                        ]
                    );

                    if ($schedule->seats()->exists()) {
                        continue;
                    }

                    foreach ($seatNumbers as $seatNumber) {
                        $seatRows[] = [
                            'schedule_id' => $schedule->id,
                            'seat_number' => $seatNumber,
                            'seat_class' => str_starts_with($seatNumber, 'A') ? 'executive' : 'economy',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }
        }

        // Insert berkelompok agar tidak menembus batas placeholder MySQL.
        foreach (array_chunk($seatRows, 500) as $chunk) {
            Seat::insert($chunk);
        }
    }
}
<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Penjadwalan
|--------------------------------------------------------------------------
|
| Pembersihan lock kedaluwarsa dijalankan tiap menit. Jalankan dengan
| `php artisan schedule:work` di terminal terpisah bila ingin melihatnya
| bekerja. Tidak wajib: lock kedaluwarsa sudah diabaikan secara lazy di setiap
| pembacaan, jadi aplikasi tetap benar tanpa scheduler.
|
*/

Schedule::command('seats:release-expired')
    ->everyMinute()
    ->withoutOverlapping();

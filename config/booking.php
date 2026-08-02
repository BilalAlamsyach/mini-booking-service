<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seat Lock TTL
    |--------------------------------------------------------------------------
    |
    | Berapa lama (dalam detik) sebuah kursi ditahan setelah pengguna
    | menguncinya. Setelah lewat batas ini lock dianggap kedaluwarsa dan kursi
    | otomatis kembali tersedia untuk pengguna lain. Nilai default 300 detik
    | (5 menit) sesuai kebutuhan studi kasus.
    |
    */

    'lock_ttl_seconds' => (int) env('SEAT_LOCK_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Batas Kursi per Transaksi
    |--------------------------------------------------------------------------
    |
    | Jumlah maksimum kursi yang boleh dikunci dalam satu permintaan. Membatasi
    | ini menjaga durasi transaksi (dan row lock) tetap pendek.
    |
    */

    'max_seats_per_booking' => (int) env('MAX_SEATS_PER_BOOKING', 6),

];

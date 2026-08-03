# Mini Booking Service

Aplikasi API sederhana untuk booking kursi dengan mekanisme concurrency handling dan autentikasi token.

## Setup & Run
1. Install dependency:
   ```bash
   composer install
   ```
2. Salin environment jika perlu:
   ```bash
   copy .env.example .env
   ```
3. Atur database MySQL di file `.env`.
4. Jalankan migrasi dan seeder:
   ```bash
   php artisan migrate
   php artisan db:seed
   ```
5. Jalankan aplikasi:
   ```bash
   php artisan serve
   ```

## API
Dokumentasi endpoint tersedia di [API.md](API.md).

## Pendekatan Concurrency
Saat banyak request mencoba mengunci kursi yang sama secara bersamaan, sistem menggunakan transaksi database dan locking untuk memastikan hanya satu request yang berhasil. Hasilnya, kursi tidak bisa diduplikasi atau dibooking oleh dua user sekaligus.

## Pendekatan Autentikasi
Sistem menggunakan token Bearer berbasis Sanctum. Setelah login, client menerima access token . Access token dipakai untuk request biasa, client juga memiliki refresh token dipakai untuk memperbarui token tanpa login ulang.

## Testing
Untuk load test concurrency:
```bash
php artisan seats:load-test
```

untuk BookingFlowTest
```bash
php artisan test
```

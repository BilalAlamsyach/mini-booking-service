# Mini Booking Service

Aplikasi API sederhana untuk booking kursi dengan mekanisme concurrency handling dan autentikasi token.

## Setup & Run
1. Install dependency:
   ```bash
   composer install
   ```
2. Setup Environment & Application Key:
   ```bash
   copy .env.example .env
   ```
   
   ```bash
   php artisan key:generate
   ```
3. Atur database MySQL di file `.env`.

5. Jalankan migrasi dan seeder:
   ```bash
   php artisan migrate
   php artisan db:seed
   ```
6. Jalankan aplikasi:
   ```bash
   php artisan serve
   ```

## API
Dokumentasi endpoint tersedia di [API.md](API.md).

## Pendekatan Concurrency
Saat banyak request mencoba mengunci kursi yang sama secara bersamaan, sistem menggunakan beberapa lapisan perlindungan: 
- Row-level locking: saat kursi dan hold terkait dibuka, sistem memakai `lockForUpdate()` sehingga request lain menunggu sampai transaksi selesai.
- Unique constraint di tabel `seat_holds`: setiap kursi hanya boleh punya satu hold aktif, sehingga race condition yang lolos dari pengecekan aplikasi tetap ditolak di level database.
- Expiry time: lock sementara memiliki batas waktu tertentu, sehingga kursi bisa kembali tersedia jika user tidak menyelesaikan booking dalam waktu yang ditentukan.
Dengan kombinasi ini, hanya satu request yang bisa menguasai kursi yang sama, dan booking ganda bisa dicegah meskipun banyak request datang bersamaan.

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

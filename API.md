# API Singkat

```http
Authorization: Bearer <token>
```
## 1. Login
**POST** `/api/auth/login`
Menggunakan body:
```json
{
  "email": "admin@example.com",
  "password": "password",
  "device_name": "web"
}
```
Mengembalikan token akses, refresh token, dan waktu kadaluarsa.

## 2. Refresh token
**POST** `/api/auth/refresh`
Body:

```json
{
  "refresh_token": "..."
}
```
Mengembalikan token baru dan refresh token baru.
## 3. Profil user
**GET** `/api/auth/me`
Mengembalikan data user yang sedang login.

## 4. Logout
**POST** `/api/auth/logout`

Mencabut token yang sedang dipakai.

## 5. Daftar route
**GET** `/api/routes`
Mengembalikan daftar route yang tersedia.

## 6. Daftar schedule
**GET** `/api/schedules`
Mengembalikan daftar jadwal yang tersedia.

## 7. Detail kursi pada schedule
**GET** `/api/schedules/{schedule}/seats`
Mengembalikan status kursi untuk jadwal tertentu.

## 8. Lock kursi
**POST** `/api/seat-locks`
Body contoh:
```json
{
  "schedule_id": 1,
  "seat_ids": [10]
}
```
Mengembalikan token lock dan waktu kadaluarsa lock.

## 9. Buka lock kursi
**DELETE** `/api/seat-locks/{lockToken}`
Melepas lock kursi yang sudah dibuat.

## 10. Daftar booking
**GET** `/api/bookings`
Mengembalikan daftar booking milik user yang sedang login.

## 11. Buat booking
**POST** `/api/bookings`
Body contoh:
```json
{
  "lock_token": "..."
}
```
Mengonfirmasi booking berdasarkan token lock.

## 12. Detail booking
**GET** `/api/bookings/{booking}`
Mengembalikan detail booking tertentu.

## 13. Testing
Beberapa skenario penting sudah diuji melalui feature test di folder `tests/Feature`.

### Test yang tersedia
- `BookingFlowTest`: menguji alur booking secara end-to-end untuk:
  - booking berhasil dengan lock valid
  - booking menolak request tanpa autentikasi
  - lock kedaluwarsa ditolak dengan error `LOCK_EXPIRED`

### Alur kerja `BookingFlowTest`
Test ini mensimulasikan tiga skenario yang dekat dengan kondisi nyata:

1. `booking can be confirmed with valid lock`
   - Input: dibuat user baru, jadwal, dan satu seat hold yang masih aktif.
   - Request yang dikirim: `POST /api/bookings` dengan `lock_token`, `passenger_name`, dan `passenger_phone`.
   - Output yang diharapkan: status `201 Created` dan pesan `Pemesanan berhasil dikonfirmasi.`

2. `booking requires authentication`
   - Input: request booking tanpa token autentikasi.
   - Request yang dikirim: `POST /api/bookings` dengan data penumpang yang sama.
   - Output yang diharapkan: status `401 Unauthorized`.

3. `expired lock cannot be confirmed`
   - Input: dibuat seat hold yang sudah kadaluarsa.
   - Request yang dikirim: `POST /api/bookings` dengan token lock tersebut.
   - Output yang diharapkan: status `410 Gone` dan error code `LOCK_EXPIRED`.

### Cara menjalankan test
```bash
php artisan test
```

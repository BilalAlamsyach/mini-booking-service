<?php

namespace App\Exceptions;

/**
 * Lock sudah lewat `expires_at` sebelum booking dikonfirmasi.
 *
 * 410 Gone membedakan kasus ini dari 409: kursinya bukan direbut orang lain,
 * melainkan waktu tahan pengguna sendiri yang habis.
 */
class LockExpiredException extends BookingException
{
    public static function make(): self
    {
        return new self('Lock kursi sudah kedaluwarsa. Silakan pilih kursi kembali.');
    }

    public function status(): int
    {
        return 410;
    }

    public function errorCode(): string
    {
        return 'LOCK_EXPIRED';
    }
}

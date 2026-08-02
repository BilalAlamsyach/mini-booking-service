<?php

namespace App\Exceptions;

/**
 * Token lock tidak dikenal, sudah dilepas, atau bukan milik pengguna ini.
 *
 * Ketiga kondisi sengaja dijawab sama (404) agar tidak membocorkan keberadaan
 * token milik pengguna lain.
 */
class LockNotFoundException extends BookingException
{
    public static function make(): self
    {
        return new self('Lock kursi tidak ditemukan atau sudah dilepas.');
    }

    public function status(): int
    {
        return 404;
    }

    public function errorCode(): string
    {
        return 'LOCK_NOT_FOUND';
    }
}

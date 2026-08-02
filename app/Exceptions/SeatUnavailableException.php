<?php

namespace App\Exceptions;

/**
 * Kursi sedang dikunci pengguna lain atau sudah dipesan.
 *
 * 409 Conflict dipilih karena permintaannya sah, hanya berbenturan dengan
 * keadaan sumber daya saat ini.
 */
class SeatUnavailableException extends BookingException
{
    /**
     * @param  list<string>  $seatNumbers  Nomor kursi yang menyebabkan konflik.
     */
    public static function forSeats(array $seatNumbers): self
    {
        $label = implode(', ', $seatNumbers);

        return new self(
            $label === ''
                ? 'Kursi sudah tidak tersedia.'
                : "Kursi {$label} sedang dikunci atau sudah dipesan pengguna lain.",
            ['unavailable_seats' => $seatNumbers]
        );
    }

    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'SEAT_UNAVAILABLE';
    }
}

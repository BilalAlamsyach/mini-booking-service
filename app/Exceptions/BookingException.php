<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Induk dari semua kegagalan alur pemesanan yang bisa diantisipasi.
 *
 * Laravel memanggil `render()` secara otomatis bila exception memilikinya
 * (lihat Illuminate\Foundation\Exceptions\Handler::render), sehingga setiap
 * turunan cukup mendeklarasikan status HTTP dan kode errornya.
 */
abstract class BookingException extends Exception
{
    /**
     * @param  array<string, mixed>  $context  Data tambahan untuk klien.
     */
    public function __construct(string $message, protected array $context = [])
    {
        parent::__construct($message);
    }

    abstract public function status(): int;

    abstract public function errorCode(): string;

    public function render(Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return response()->json(array_merge([
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode(),
        ], $this->context), $this->status());
    }
}

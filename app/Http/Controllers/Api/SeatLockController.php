<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\LockNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LockSeatsRequest;
use App\Services\SeatLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeatLockController extends Controller
{
    public function __construct(private readonly SeatLockService $seatLocks) {}

    /**
     * (c) Kunci kursi sementara selama TTL yang dikonfigurasi (default 5 menit).
     * Terproteksi.
     */
    public function store(LockSeatsRequest $request): JsonResponse
    {
        $result = $this->seatLocks->lock(
            $request->user(),
            $request->integer('schedule_id'),
            $request->input('seat_ids'),
        );

        return response()->json([
            'message' => 'Kursi berhasil dikunci.',
            'data' => [
                'lock_token' => $result['lock_token'],
                'expires_at' => $result['expires_at']->toIso8601String(),
                'expires_in_seconds' => (int) config('booking.lock_ttl_seconds'),
                'schedule_id' => $request->integer('schedule_id'),
                'seats' => $result['seats']->map(fn ($seat) => [
                    'id' => $seat->id,
                    'seat_number' => $seat->seat_number,
                    'seat_class' => $seat->seat_class,
                ])->values(),
            ],
        ], 201);
    }

    /**
     * Lepas lock lebih awal, mis. saat pengguna membatalkan pilihan kursi.
     * Terproteksi.
     */
    public function destroy(Request $request, string $lockToken): JsonResponse
    {
        $released = $this->seatLocks->release($request->user(), $lockToken);

        if ($released === 0) {
            throw LockNotFoundException::make();
        }

        return response()->json([
            'message' => 'Lock dilepas.',
            'released_seats' => $released,
        ]);
    }
}

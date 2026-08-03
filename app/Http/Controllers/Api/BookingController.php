<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}


    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::query()
            ->where('user_id', $request->user()->id)
            ->with(['seats', 'schedule.route.operator'])
            ->latest('id')
            ->get();

        return response()->json([
            'data' => BookingResource::collection($bookings)->resolve(),
        ]);
    }


    public function store(ConfirmBookingRequest $request): JsonResponse
    {
        $booking = $this->bookings->confirm(
            $request->user(),
            $request->string('lock_token')->value(),
            [
                'passenger_name' => $request->string('passenger_name')->value(),
                'passenger_phone' => $request->string('passenger_phone')->value(),
            ],
        );

        return response()->json([
            'message' => 'Pemesanan berhasil dikonfirmasi.',
            'data' => (new BookingResource($booking))->resolve(),
        ], 201);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        abort_if($booking->user_id !== $request->user()->id, 404);

        $booking->load(['seats', 'schedule.route.operator']);

        return response()->json([
            'data' => (new BookingResource($booking))->resolve(),
        ]);
    }
}

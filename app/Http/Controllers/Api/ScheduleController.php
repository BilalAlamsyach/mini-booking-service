<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Models\Schedule;
use App\Models\SeatHold;
use App\Services\SeatLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function __construct(private readonly SeatLockService $seatLocks) {}

    public function index(SearchScheduleRequest $request): JsonResponse
    {
        $schedules = Schedule::query()
            ->with('route.operator')
            ->withCount([
                'seats',
                'seats as available_seats_count' => fn ($query) => $query
                    ->whereDoesntHave('hold', fn ($hold) => $hold
                        ->where('status', SeatHold::STATUS_BOOKED)
                        ->orWhere(fn ($active) => $active
                            ->where('status', SeatHold::STATUS_LOCKED)
                            ->where('expires_at', '>', now())
                        )
                    ),
            ])
            ->whereDate('departure_date', $request->date('date'))
            ->when($request->filled('route_id'), fn ($query) => $query->where('route_id', $request->integer('route_id')))
            ->when($request->filled('origin'), fn ($query) => $query->whereRelation('route', 'origin', $request->string('origin')))
            ->when($request->filled('destination'), fn ($query) => $query->whereRelation('route', 'destination', $request->string('destination')))
            ->orderBy('departure_time')
            ->get();

        return response()->json(['data' => ScheduleResource::collection($schedules)->resolve()]);
    }


    public function seats(Request $request, Schedule $schedule): JsonResponse
    {
        $schedule->load('route.operator');

        // Guard dipanggil manual — endpoint ini publik, token hanya opsional.
        $userId = $request->user('sanctum')?->id;

        return response()->json([
            'data' => [
                'schedule' => (new ScheduleResource($schedule))->resolve(),
                'lock_ttl_seconds' => (int) config('booking.lock_ttl_seconds'),
                'seats' => $this->seatLocks->availability($schedule->id, $userId),
            ],
        ]);
    }
}

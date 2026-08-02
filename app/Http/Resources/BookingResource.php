<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Booking
 */
class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_code' => $this->booking_code,
            'status' => $this->status,
            'total_price' => (float) $this->total_price,
            'passenger_name' => $this->passenger_name,
            'passenger_phone' => $this->passenger_phone,
            'created_at' => $this->created_at->toIso8601String(),
            'seats' => $this->whenLoaded('seats', fn () => $this->seats
                ->map(fn ($seat) => [
                    'id' => $seat->id,
                    'seat_number' => $seat->seat_number,
                    'seat_class' => $seat->seat_class,
                    'price' => (float) $seat->pivot->price,
                ])
                ->values()
            ),
            'schedule' => new ScheduleResource($this->whenLoaded('schedule')),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Schedule
 */
class ScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_code' => $this->vehicle_code,
            'departure_date' => $this->departure_date->toDateString(),
            'departure_time' => substr((string) $this->departure_time, 0, 5),
            'arrival_time' => substr((string) $this->arrival_time, 0, 5),
            'price' => (float) $this->price,
            // Hanya ada bila query memakai withCount pada controller.
            'available_seats' => $this->whenCounted('availableSeats'),
            'total_seats' => $this->whenCounted('seats'),
            'route' => $this->whenLoaded('route', fn () => [
                'id' => $this->route->id,
                'origin' => $this->route->origin,
                'destination' => $this->route->destination,
                'duration_minutes' => $this->route->duration_minutes,
                'operator' => $this->whenLoaded('route', fn () => [
                    'id' => $this->route->operator->id,
                    'code' => $this->route->operator->code,
                    'name' => $this->route->operator->name,
                ]),
            ]),
        ];
    }
}

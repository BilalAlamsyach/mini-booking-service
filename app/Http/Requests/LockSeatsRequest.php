<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LockSeatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $max = (int) config('booking.max_seats_per_booking');

        return [
            'schedule_id' => ['required', 'integer', 'exists:schedules,id'],
            'seat_ids' => ['required', 'array', 'min:1', "max:{$max}"],
            'seat_ids.*' => ['required', 'integer', 'distinct', 'exists:seats,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'schedule_id.required' => 'Jadwal wajib dipilih.',
            'schedule_id.exists' => 'Jadwal tidak ditemukan.',
            'seat_ids.required' => 'Pilih minimal satu kursi.',
            'seat_ids.max' => 'Jumlah kursi melebihi batas maksimum.',
            'seat_ids.*.distinct' => 'Terdapat kursi yang dipilih lebih dari sekali.',
            'seat_ids.*.exists' => 'Salah satu kursi tidak ditemukan.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Tanggal wajib; penyaringan rute boleh lewat `route_id` atau pasangan
     * `origin`/`destination`. Tanpa keduanya, semua jadwal pada tanggal itu
     * dikembalikan.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'route_id' => ['sometimes', 'integer', 'exists:routes,id'],
            'origin' => ['sometimes', 'string', 'max:100'],
            'destination' => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Tanggal keberangkatan wajib diisi.',
            'date.date_format' => 'Format tanggal harus YYYY-MM-DD.',
            'route_id.exists' => 'Rute tidak ditemukan.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmBookingRequest extends FormRequest
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
        return [
            'lock_token' => ['required', 'uuid'],
            'passenger_name' => ['required', 'string', 'max:255'],
            'passenger_phone' => ['required', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'lock_token.required' => 'Token lock wajib disertakan.',
            'lock_token.uuid' => 'Format token lock tidak valid.',
            'passenger_name.required' => 'Nama penumpang wajib diisi.',
            'passenger_phone.required' => 'Nomor telepon penumpang wajib diisi.',
        ];
    }
}

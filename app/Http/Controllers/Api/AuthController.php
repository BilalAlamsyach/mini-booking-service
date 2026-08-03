<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        $deviceName = $request->string('device_name', 'web')->value();
        $user->tokens()->where('name', $deviceName)->delete();

        $accessToken = $user->createToken($deviceName, ['*']);
        $accessToken->accessToken->expires_at = now()->addMinutes(30);
        $accessToken->accessToken->save();

        $refreshToken = $user->createToken($deviceName.'-refresh', ['refresh']);
        $refreshToken->accessToken->expires_at = now()->addDays(7);
        $refreshToken->accessToken->save();

        return response()->json([
            'message' => 'Login berhasil.',
            'token_type' => 'Bearer',
            'token' => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken->plainTextToken,
            'expires_at' => $accessToken->accessToken->expires_at->toIso8601String(),
            'refresh_expires_at' => $refreshToken->accessToken->expires_at->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $token = PersonalAccessToken::findToken($request->string('refresh_token')->value());

        if (! $token || ! $token->tokenable || $token->expires_at?->isPast() || ! $token->can('refresh')) {
            return response()->json([
                'message' => 'Refresh token tidak valid atau sudah kedaluwarsa.',
                'error_code' => 'INVALID_REFRESH_TOKEN',
            ], 401);
        }

        $user = $token->tokenable;
        $deviceName = $token->name;

        $token->delete();

        $accessToken = $user->createToken($deviceName, ['*']);
        $accessToken->accessToken->expires_at = now()->addMinutes(30);
        $accessToken->accessToken->save();

        $refreshToken = $user->createToken($deviceName.'-refresh', ['refresh']);
        $refreshToken->accessToken->expires_at = now()->addDays(7);
        $refreshToken->accessToken->save();

        return response()->json([
            'message' => 'Token berhasil diperbarui.',
            'token_type' => 'Bearer',
            'token' => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken->plainTextToken,
            'expires_at' => $accessToken->accessToken->expires_at->toIso8601String(),
            'refresh_expires_at' => $refreshToken->accessToken->expires_at->toIso8601String(),
        ]);
    }

    /**
     * Cabut token yang sedang dipakai.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    /**
     * Profil pemilik token — dipakai frontend untuk memvalidasi sesi tersimpan.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}

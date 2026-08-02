<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTokenRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_access_and_refresh_tokens_and_refresh_endpoint_issues_new_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'token@example.com',
            'password' => 'password',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'mobile',
        ]);

        $loginResponse->assertOk();
        $loginData = $loginResponse->json();

        $this->assertNotEmpty($loginData['token']);
        $this->assertNotEmpty($loginData['refresh_token']);
        $this->assertNotNull($loginData['expires_at']);
        $this->assertNotNull($loginData['refresh_expires_at']);

        $refreshResponse = $this->postJson('/api/auth/refresh', [
            'refresh_token' => $loginData['refresh_token'],
        ]);

        $refreshResponse->assertOk();
        $refreshData = $refreshResponse->json();

        $this->assertNotEmpty($refreshData['token']);
        $this->assertNotEmpty($refreshData['refresh_token']);
        $this->assertNotSame($loginData['token'], $refreshData['token']);
        $this->assertNotSame($loginData['refresh_token'], $refreshData['refresh_token']);
        $this->assertNotNull($refreshData['expires_at']);
        $this->assertNotNull($refreshData['refresh_expires_at']);
    }

    public function test_expired_access_token_is_rejected_for_protected_route(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('web');
        $token->accessToken->expires_at = now()->subMinute();
        $token->accessToken->save();

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/auth/me');

        $response->assertUnauthorized();
        $response->assertJsonFragment(['error_code' => 'UNAUTHENTICATED']);
    }
}

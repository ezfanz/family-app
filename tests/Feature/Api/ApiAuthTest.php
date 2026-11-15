<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/trips')->assertUnauthorized();
    }

    public function test_mobile_app_can_get_a_token_and_use_it(): void
    {
        User::factory()->create(['email' => 'qi@example.com', 'password' => 'sakura-2026']);

        $token = $this->postJson('/api/auth/token', [
            'email' => 'QI@example.com',
            'password' => 'sakura-2026',
            'device_name' => 'Pixel 9',
        ])->assertCreated()->assertJsonPath('user.email', 'qi@example.com')->json('token');

        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('email', 'qi@example.com');
    }

    public function test_wrong_password_gets_no_token(): void
    {
        User::factory()->create(['email' => 'qi@example.com', 'password' => 'sakura-2026']);

        $this->postJson('/api/auth/token', [
            'email' => 'qi@example.com',
            'password' => 'nope',
            'device_name' => 'Pixel 9',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_a_token_can_be_revoked(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/auth/token')->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_session_login_also_works_for_the_api(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/trips')->assertOk();
    }
}

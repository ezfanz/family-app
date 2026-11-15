<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('trips.index'))->assertRedirect(route('login'));
    }

    public function test_login_and_register_pages_render(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Log in');
        $this->get(route('register'))->assertOk()->assertSee('Make an account');
    }

    public function test_a_person_can_register(): void
    {
        $this->post(route('register'), [
            'name' => 'Aina',
            'email' => ' Aina@Example.com ',
            'password' => 'sakura-2026',
            'password_confirmation' => 'sakura-2026',
        ])->assertRedirect(route('trips.index'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'aina@example.com', 'name' => 'Aina']);
    }

    public function test_registration_validates_input(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('register'), [
            'name' => '',
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['name', 'email', 'password']);

        $this->assertGuest();
    }

    public function test_a_person_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['email' => 'qi@example.com', 'password' => 'sakura-2026']);

        $this->post(route('login'), ['email' => 'QI@example.com', 'password' => 'sakura-2026'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'qi@example.com', 'password' => 'sakura-2026']);

        $this->from(route('login'))
            ->post(route('login'), ['email' => 'qi@example.com', 'password' => 'nope'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'qi@example.com']);

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('login'), ['email' => 'qi@example.com', 'password' => 'nope']);
        }

        $this->post(route('login'), ['email' => 'qi@example.com', 'password' => 'nope'])
            ->assertTooManyRequests();
    }
}

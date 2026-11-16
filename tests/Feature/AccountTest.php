<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_account(): void
    {
        $this->get(route('account'))->assertRedirect(route('login'));
    }

    public function test_account_shows_profile_trips_and_preferences(): void
    {
        ['trip' => $trip, 'owner' => $owner] = $this->tripFixture();

        $this->actingAs($owner)
            ->from(route('trips.show', $trip))
            ->get(route('account'))
            ->assertOk()
            ->assertSee($owner->email)
            ->assertSee($trip->name)
            ->assertSee('Current')
            ->assertSee('Itinerary')
            ->assertSee('Join or start another trip')
            ->assertSee('Appearance')
            ->assertSee('value="system" data-label="Auto" checked', false)
            ->assertSee('Save trip on this phone')
            ->assertSee('Log out of E-Itinerary?');
    }

    public function test_the_theme_cookie_is_applied_when_the_page_is_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withUnencryptedCookie('theme', 'dark')
            ->get(route('account'))
            ->assertSee('<html lang="en" data-theme="dark">', false)
            ->assertSee('value="dark" data-label="Dark" checked', false);

        $this->actingAs($user)
            ->withUnencryptedCookie('theme', 'light')
            ->get(route('trips.index'))
            ->assertSee('<html lang="en" data-theme="light">', false);
    }

    public function test_unknown_theme_values_are_ignored(): void
    {
        $this->actingAs(User::factory()->create())
            ->withUnencryptedCookie('theme', '"><script>alert(1)</script>')
            ->get(route('account'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('value="system" data-label="Auto" checked', false);
    }

    public function test_group_page_links_to_account(): void
    {
        ['trip' => $trip, 'viewer' => $viewer] = $this->tripFixture();

        $this->actingAs($viewer)->get(route('trips.members.index', $trip))
            ->assertOk()
            ->assertSee('Account and log out')
            ->assertSee(route('account'), false);
    }
}

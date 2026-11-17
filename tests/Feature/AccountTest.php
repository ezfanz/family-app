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

    public function test_editors_get_the_todays_rate_switch_for_their_trip(): void
    {
        ['trip' => $trip, 'editor' => $editor, 'viewer' => $viewer] = $this->tripFixture();
        $trip->update(['exchange_rate_auto' => false]);

        $this->actingAs($editor)->get(route('account'))
            ->assertOk()
            ->assertSee('Today’s exchange rate', false)
            ->assertSee('data-trip-auto-rate="'.$trip->id.'"', false)
            ->assertDontSee('data-trip-auto-rate="'.$trip->id.'" checked', false);

        $trip->update(['exchange_rate_auto' => true]);
        $this->actingAs($editor)->get(route('account'))
            ->assertSee('data-trip-auto-rate="'.$trip->id.'" checked', false);

        $this->actingAs($viewer)->get(route('account'))
            ->assertDontSee('data-trip-auto-rate', false);
    }

    public function test_people_can_change_the_name_others_see(): void
    {
        ['trip' => $trip, 'owner' => $owner] = $this->tripFixture();

        $this->actingAs($owner)->from(route('account'))
            ->patch(route('account.name'), ['name' => '  Irfan  '])
            ->assertRedirect(route('account'))
            ->assertSessionHas('status', 'Name saved. Everyone in your trips now sees “Irfan”.');
        $this->assertSame('Irfan', $owner->fresh()->name);

        // Others see the new name on the trip.
        $this->actingAs($owner)->getJson("/api/trips/{$trip->id}")
            ->assertJsonFragment(['name' => 'Irfan']);

        $this->actingAs($owner)->from(route('account'))
            ->patch(route('account.name'), ['name' => ' '])
            ->assertSessionHasErrors('name');
        $this->actingAs($owner)->from(route('account'))
            ->patch(route('account.name'), ['name' => str_repeat('a', 81)])
            ->assertSessionHasErrors('name');
        $this->assertSame('Irfan', $owner->fresh()->name);
    }

    public function test_after_saving_the_name_the_link_still_goes_back_to_the_trip(): void
    {
        ['trip' => $trip, 'owner' => $owner] = $this->tripFixture();

        $this->actingAs($owner)->from(route('trips.show', $trip))->get(route('account'))->assertOk();
        $this->actingAs($owner)->from(route('account'))->patch(route('account.name'), ['name' => 'Irfan']);

        $this->actingAs($owner)->from(route('account'))->get(route('account'))
            ->assertSee('<a class="back-link" href="'.route('trips.show', $trip).'">', false);
    }

    public function test_guests_cannot_change_a_name(): void
    {
        $this->patch(route('account.name'), ['name' => 'Someone'])->assertRedirect(route('login'));
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

    public function test_the_sakura_theme_is_applied_and_preselected(): void
    {
        $this->actingAs(User::factory()->create())
            ->withUnencryptedCookie('theme', 'sakura')
            ->get(route('account'))
            ->assertSee('<html lang="en" data-theme="sakura">', false)
            ->assertSee('<meta name="theme-color" content="#C2255C">', false)
            ->assertSee('value="sakura" data-label="Sakura" checked', false);
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

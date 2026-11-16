<?php

namespace Tests\Feature;

use App\Enums\TripRole;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_page_renders_the_app_shell(): void
    {
        ['trip' => $trip, 'viewer' => $viewer] = $this->tripFixture();

        $this->actingAs($viewer)->get(route('trips.show', $trip))
            ->assertOk()
            ->assertSee('data-trip-id="'.$trip->id.'"', false)
            ->assertSee('id="rail"', false)
            ->assertSee('id="actDlg"', false)
            ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
            ->assertSee('Itinerary')
            ->assertSee('Shopping')
            ->assertSee('Ideas');
    }

    public function test_a_user_can_start_a_new_trip_from_the_web(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('trips.create'))->assertOk()->assertSee('Start a new trip');

        $response = $this->actingAs($user)->post(route('trips.store'), [
            'name' => 'Korea 2027',
            'start_date' => '2027-03-01',
            'end_date' => '2027-03-03',
        ]);

        $trip = Trip::where('name', 'Korea 2027')->sole();
        $response->assertRedirect(route('trips.show', $trip));
        $this->assertSame(3, $trip->days()->count());
        $this->assertSame(TripRole::Owner, $trip->roleFor($user));
    }

    public function test_join_screen_shows_for_people_without_trips(): void
    {
        $this->actingAs(User::factory()->create())->get(route('trips.index'))
            ->assertOk()
            ->assertSee('Join a trip')
            ->assertSee('Start a new trip');
    }

    public function test_group_page_shows_roles_and_owner_controls(): void
    {
        ['trip' => $trip, 'owner' => $owner, 'viewer' => $viewer] = $this->tripFixture();

        $this->actingAs($owner)->get(route('trips.members.index', $trip))
            ->assertOk()
            ->assertSee('Copy code')
            ->assertSee('Role for '.$viewer->name)
            ->assertSee($viewer->email);

        $this->actingAs($viewer)->get(route('trips.members.index', $trip))
            ->assertOk()
            ->assertDontSee('Copy code')
            ->assertDontSee($owner->email);
    }
}

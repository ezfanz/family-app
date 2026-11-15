<?php

namespace Tests\Feature;

use App\Enums\TripRole;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripSharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_join_a_trip_with_its_code(): void
    {
        $trip = Trip::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('trips.join'), ['code' => strtolower(substr($trip->join_code, 0, 4).' '.substr($trip->join_code, 4))])
            ->assertRedirect(route('trips.show', $trip));

        $this->assertSame(TripRole::Editor, $trip->fresh()->roleFor($user));
    }

    public function test_joining_twice_keeps_the_existing_role(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->withMember($user, TripRole::Viewer)->create();

        $this->actingAs($user)
            ->post(route('trips.join'), ['code' => $trip->join_code])
            ->assertSessionHas('status', 'You’re already in this trip.');

        $this->assertSame(TripRole::Viewer, $trip->fresh()->roleFor($user));
    }

    public function test_a_wrong_code_is_rejected(): void
    {
        Trip::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('trips.join'), ['code' => 'ZZZZZZZZ'])
            ->assertSessionHasErrors('code');
    }

    public function test_non_members_cannot_see_a_trip(): void
    {
        $trip = Trip::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('trips.show', $trip))
            ->assertNotFound();
    }

    public function test_home_opens_the_only_trip(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->withMember($user, TripRole::Editor)->create();

        $this->actingAs($user)->get('/')->assertRedirect(route('trips.show', $trip));
    }

    public function test_only_the_owner_sees_the_join_code(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $trip = Trip::factory()
            ->withMember($owner, TripRole::Owner)
            ->withMember($editor, TripRole::Editor)
            ->create();

        $this->actingAs($owner)->get(route('trips.members.index', $trip))
            ->assertOk()->assertSee($trip->join_code);

        $this->actingAs($editor)->get(route('trips.members.index', $trip))
            ->assertOk()->assertDontSee($trip->join_code);
    }

    public function test_owner_can_change_roles_and_remove_people(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $trip = Trip::factory()
            ->withMember($owner, TripRole::Owner)
            ->withMember($member, TripRole::Editor)
            ->create();

        $this->actingAs($owner)
            ->patch(route('trips.members.update', [$trip, $member]), ['role' => 'viewer'])
            ->assertRedirect();
        $this->assertSame(TripRole::Viewer, $trip->fresh()->roleFor($member));

        $this->actingAs($owner)
            ->delete(route('trips.members.destroy', [$trip, $member]))
            ->assertRedirect();
        $this->assertNull($trip->fresh()->roleFor($member));
    }

    public function test_owner_cannot_demote_themselves(): void
    {
        $owner = User::factory()->create();
        $trip = Trip::factory()->withMember($owner, TripRole::Owner)->create();

        $this->actingAs($owner)
            ->patch(route('trips.members.update', [$trip, $owner]), ['role' => 'viewer'])
            ->assertUnprocessable();

        $this->assertSame(TripRole::Owner, $trip->fresh()->roleFor($owner));
    }

    public function test_editors_and_viewers_cannot_manage_people(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $viewer = User::factory()->create();
        $trip = Trip::factory()
            ->withMember($owner, TripRole::Owner)
            ->withMember($editor, TripRole::Editor)
            ->withMember($viewer, TripRole::Viewer)
            ->create();

        foreach ([$editor, $viewer] as $user) {
            $this->actingAs($user)
                ->patch(route('trips.members.update', [$trip, $owner]), ['role' => 'viewer'])
                ->assertForbidden();
            $this->actingAs($user)
                ->post(route('trips.join-code.regenerate', $trip))
                ->assertForbidden();
        }

        $this->assertSame(TripRole::Owner, $trip->fresh()->roleFor($owner));
    }

    public function test_owner_can_regenerate_the_join_code(): void
    {
        $owner = User::factory()->create();
        $trip = Trip::factory()->withMember($owner, TripRole::Owner)->create();
        $oldCode = $trip->join_code;

        $this->actingAs($owner)->post(route('trips.join-code.regenerate', $trip))->assertRedirect();

        $this->assertNotSame($oldCode, $trip->fresh()->join_code);
        $this->actingAs(User::factory()->create())
            ->post(route('trips.join'), ['code' => $oldCode])
            ->assertSessionHasErrors('code');
    }

    public function test_trip_owner_command_promotes_a_user(): void
    {
        $user = User::factory()->create(['email' => 'organiser@example.com']);
        $trip = Trip::factory()->create();

        $this->artisan('trip:owner', ['email' => 'Organiser@example.com'])->assertSuccessful();

        $this->assertSame(TripRole::Owner, $trip->fresh()->roleFor($user));
    }
}

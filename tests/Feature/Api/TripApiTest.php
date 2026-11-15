<?php

namespace Tests\Feature\Api;

use App\Enums\TripRole;
use App\Models\ActivityNote;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_my_trips(): void
    {
        ['trip' => $trip, 'editor' => $editor] = $this->tripFixture();
        Trip::factory()->create();

        $this->actingAs($editor)->getJson('/api/trips')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $trip->id)
            ->assertJsonPath('data.0.my_role', 'editor')
            ->assertJsonMissingPath('data.0.join_code');
    }

    public function test_full_payload_has_everything_and_only_my_notes(): void
    {
        ['trip' => $trip, 'owner' => $owner, 'editor' => $editor, 'activity' => $activity] = $this->tripFixture();
        ActivityNote::factory()->create(['activity_id' => $activity->id, 'user_id' => $owner->id, 'note' => 'owner secret']);
        ActivityNote::factory()->create(['activity_id' => $activity->id, 'user_id' => $editor->id, 'note' => 'mine']);

        $this->actingAs($editor)->getJson("/api/trips/{$trip->id}")
            ->assertOk()
            ->assertJsonPath('trip.my_role', 'editor')
            ->assertJsonPath('days.0.activities.0.name', 'Kuromon Market')
            ->assertJsonPath('days.0.activities.0.time', '09:00')
            ->assertJsonPath('shopping_items.0.name', 'Royce Nama Chocolate')
            ->assertJsonPath('ideas.0.name', 'Tokyo Tower')
            ->assertJsonCount(1, 'my_notes')
            ->assertJsonPath('my_notes.0.note', 'mine')
            ->assertJsonStructure(['server_time']);
    }

    public function test_owner_sees_the_join_code(): void
    {
        ['trip' => $trip, 'owner' => $owner] = $this->tripFixture();

        $this->actingAs($owner)->getJson("/api/trips/{$trip->id}")
            ->assertJsonPath('trip.join_code', $trip->join_code);
    }

    public function test_outsiders_get_not_found(): void
    {
        ['trip' => $trip, 'outsider' => $outsider] = $this->tripFixture();

        $this->actingAs($outsider)->getJson("/api/trips/{$trip->id}")->assertNotFound();
        $this->actingAs($outsider)->getJson("/api/trips/{$trip->id}/changes?since=2026-01-01T00:00:00Z")->assertNotFound();
    }

    public function test_join_by_code_makes_you_a_viewer(): void
    {
        ['trip' => $trip] = $this->tripFixture();
        $newcomer = User::factory()->create();

        $this->actingAs($newcomer)->postJson('/api/trips/join', ['code' => strtolower($trip->join_code)])
            ->assertCreated()
            ->assertJsonPath('data.my_role', 'viewer');

        $this->actingAs($newcomer)->postJson('/api/trips/join', ['code' => $trip->join_code])->assertOk();
        $this->actingAs($newcomer)->postJson('/api/trips/join', ['code' => 'ZZZZZZZZ'])->assertUnprocessable();
    }

    public function test_starting_a_trip_creates_blank_days_and_makes_you_owner(): void
    {
        $user = User::factory()->create();

        $id = $this->actingAs($user)->postJson('/api/trips', [
            'name' => 'Korea 2027',
            'start_date' => '2027-03-01',
            'end_date' => '2027-03-05',
        ])->assertCreated()->assertJsonPath('data.my_role', 'owner')->json('data.id');

        $trip = Trip::findOrFail($id);
        $this->assertSame(5, $trip->days()->count());
        $this->assertSame(TripRole::Owner, $trip->roleFor($user));
    }

    public function test_trips_cannot_be_longer_than_sixty_days(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/api/trips', [
            'name' => 'Too long',
            'start_date' => '2027-01-01',
            'end_date' => '2027-06-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('end_date');
    }

    public function test_editors_can_change_rate_and_deposit_but_viewers_cannot(): void
    {
        ['trip' => $trip, 'editor' => $editor, 'viewer' => $viewer] = $this->tripFixture();

        $this->actingAs($editor)->patchJson("/api/trips/{$trip->id}", ['exchange_rate_rm_per_100_yen' => 3.15, 'deposit_rm' => 800])
            ->assertOk()
            ->assertJsonPath('data.exchange_rate_rm_per_100_yen', 3.15)
            ->assertJsonPath('data.deposit_rm', 800);

        $this->actingAs($viewer)->patchJson("/api/trips/{$trip->id}", ['deposit_rm' => 1])->assertForbidden();
        $this->assertEquals(800, $trip->fresh()->deposit_rm);
    }

    public function test_owner_manages_members_through_the_api(): void
    {
        ['trip' => $trip, 'owner' => $owner, 'viewer' => $viewer, 'editor' => $editor] = $this->tripFixture();

        $this->actingAs($owner)->getJson("/api/trips/{$trip->id}/members")
            ->assertOk()->assertJsonCount(3, 'data')->assertJsonStructure(['data' => [['email']]]);
        $this->actingAs($editor)->getJson("/api/trips/{$trip->id}/members")
            ->assertOk()->assertJsonMissingPath('data.0.email');

        $this->actingAs($owner)->patchJson("/api/trips/{$trip->id}/members/{$viewer->id}", ['role' => 'editor'])
            ->assertOk()->assertJsonPath('data.role', 'editor');
        $this->actingAs($owner)->patchJson("/api/trips/{$trip->id}/members/{$viewer->id}", ['role' => 'owner'])
            ->assertUnprocessable();
        $this->actingAs($editor)->patchJson("/api/trips/{$trip->id}/members/{$viewer->id}", ['role' => 'viewer'])
            ->assertForbidden();

        $oldCode = $trip->join_code;
        $this->actingAs($owner)->postJson("/api/trips/{$trip->id}/join-code")->assertOk();
        $this->assertNotSame($oldCode, $trip->fresh()->join_code);

        $this->actingAs($owner)->deleteJson("/api/trips/{$trip->id}/members/{$viewer->id}")->assertNoContent();
        $this->assertNull($trip->fresh()->roleFor($viewer));
    }
}

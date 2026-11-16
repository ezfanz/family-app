<?php

namespace Tests\Feature\Api;

use App\Models\ActivityNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_updates_and_deletions_since_the_given_time(): void
    {
        $this->travelTo('2026-11-21 00:00:00');
        $fixture = $this->tripFixture();
        ['trip' => $trip, 'editor' => $editor, 'owner' => $owner, 'activity' => $activity, 'item' => $item, 'idea' => $idea] = $fixture;

        $this->travelTo('2026-11-21 01:00:00');
        $since = $this->actingAs($owner)->getJson("/api/trips/{$trip->id}")->json('server_time');

        $this->travelTo('2026-11-21 02:00:00');
        $this->actingAs($editor)->patchJson("/api/activities/{$activity->id}", ['name' => 'Kuromon Ichiba'])->assertOk();
        $this->actingAs($editor)->deleteJson("/api/shopping-items/{$item->id}")->assertNoContent();
        $this->actingAs($editor)->deleteJson("/api/ideas/{$idea->id}")->assertNoContent();

        $response = $this->actingAs($owner)->getJson("/api/trips/{$trip->id}/changes?since=".urlencode($since))
            ->assertOk()
            ->assertJsonCount(1, 'activities')
            ->assertJsonPath('activities.0.name', 'Kuromon Ichiba')
            ->assertJsonPath('activities.0.deleted_at', null)
            ->assertJsonCount(1, 'shopping_items')
            ->assertJsonPath('shopping_items.0.id', $item->id)
            ->assertJsonCount(1, 'ideas')
            ->assertJsonPath('ideas.0.id', $idea->id);

        $this->assertNotNull($response->json('shopping_items.0.deleted_at'));
        $this->assertNotNull($response->json('ideas.0.deleted_at'));
        $this->assertSame('2026-11-21T01:59:55+00:00', $response->json('server_time'));
    }

    public function test_it_returns_nothing_when_nothing_changed(): void
    {
        $this->travelTo('2026-11-21 00:00:00');
        ['trip' => $trip, 'viewer' => $viewer] = $this->tripFixture();

        $this->travelTo('2026-11-21 03:00:00');
        $this->actingAs($viewer)->getJson("/api/trips/{$trip->id}/changes?since=2026-11-21T02:00:00Z")
            ->assertOk()
            ->assertJsonCount(0, 'activities')
            ->assertJsonCount(0, 'shopping_items')
            ->assertJsonCount(0, 'ideas')
            ->assertJsonCount(0, 'my_notes');
    }

    public function test_it_only_includes_my_own_private_notes(): void
    {
        $this->travelTo('2026-11-21 00:00:00');
        ['trip' => $trip, 'viewer' => $viewer, 'editor' => $editor, 'activity' => $activity] = $this->tripFixture();

        $this->travelTo('2026-11-21 02:00:00');
        ActivityNote::factory()->create(['activity_id' => $activity->id, 'user_id' => $editor->id, 'note' => 'not yours']);
        $this->actingAs($viewer)->putJson("/api/activities/{$activity->id}/note", ['note' => 'mine'])->assertOk();

        $this->actingAs($viewer)->getJson("/api/trips/{$trip->id}/changes?since=2026-11-21T01:00:00Z")
            ->assertJsonCount(1, 'my_notes')
            ->assertJsonPath('my_notes.0.note', 'mine');
    }

    public function test_since_is_required(): void
    {
        ['trip' => $trip, 'viewer' => $viewer] = $this->tripFixture();

        $this->actingAs($viewer)->getJson("/api/trips/{$trip->id}/changes")
            ->assertUnprocessable()->assertJsonValidationErrors('since');
    }
}

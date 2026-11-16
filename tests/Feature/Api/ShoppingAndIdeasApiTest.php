<?php

namespace Tests\Feature\Api;

use App\Models\Day;
use App\Models\ShoppingItem;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShoppingAndIdeasApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_manage_their_own_shopping_list(): void
    {
        ['viewer' => $viewer, 'trip' => $trip] = $this->tripFixture();
        $id = (string) Str::uuid7();

        $this->actingAs($viewer)->postJson('/api/shopping-items', [
            'id' => $id,
            'trip_id' => $trip->id,
            'name' => 'Tokyo Banana, 8 pcs',
            'for_whom' => 'For Mak',
            'qty' => 2,
            'estimated_yen' => 2400,
        ])->assertCreated()
            ->assertJsonPath('data.for_whom', 'For Mak')
            ->assertJsonPath('data.user_id', $viewer->id);

        $this->actingAs($viewer)->patchJson("/api/shopping-items/{$id}", ['is_bought' => true, 'actual_yen' => 2380])
            ->assertOk()
            ->assertJsonPath('data.is_bought', true)
            ->assertJsonPath('data.actual_yen', 2380);

        $this->actingAs($viewer)->deleteJson("/api/shopping-items/{$id}")->assertNoContent();
        $this->assertSoftDeleted('shopping_items', ['id' => $id]);
    }

    public function test_members_cannot_touch_someone_elses_list(): void
    {
        ['viewer' => $viewer, 'editor' => $editor, 'trip' => $trip, 'item' => $editorsItem] = $this->tripFixture();

        $this->actingAs($viewer)->patchJson("/api/shopping-items/{$editorsItem->id}", ['is_bought' => true])->assertForbidden();
        $this->actingAs($viewer)->deleteJson("/api/shopping-items/{$editorsItem->id}")->assertForbidden();
        $this->actingAs($viewer)->postJson('/api/shopping-items', ['trip_id' => $trip->id, 'user_id' => $editor->id, 'name' => 'x'])->assertForbidden();

        $this->assertFalse(ShoppingItem::find($editorsItem->id)->is_bought);
    }

    public function test_owner_sees_and_manages_everyones_shopping(): void
    {
        ['owner' => $owner, 'editor' => $editor, 'trip' => $trip, 'item' => $editorsItem] = $this->tripFixture();

        $this->actingAs($owner)->getJson("/api/trips/{$trip->id}")
            ->assertJsonCount(1, 'shopping_items')
            ->assertJsonCount(3, 'shoppers');

        $this->actingAs($owner)->patchJson("/api/shopping-items/{$editorsItem->id}", ['actual_yen' => 2500])->assertOk();
        $this->actingAs($owner)->postJson('/api/shopping-items', ['trip_id' => $trip->id, 'user_id' => $editor->id, 'name' => 'KitKat'])
            ->assertCreated()->assertJsonPath('data.user_id', $editor->id);
    }

    public function test_others_only_receive_their_own_shopping(): void
    {
        ['viewer' => $viewer, 'editor' => $editor, 'trip' => $trip] = $this->tripFixture();

        $this->actingAs($viewer)->getJson("/api/trips/{$trip->id}")
            ->assertJsonCount(0, 'shopping_items')
            ->assertJsonCount(1, 'shoppers')
            ->assertJsonPath('shoppers.0.user_id', $viewer->id);

        $this->actingAs($editor)->getJson("/api/trips/{$trip->id}/changes?since=2000-01-01T00:00:00Z")
            ->assertJsonCount(1, 'shopping_items')
            ->assertJsonCount(1, 'shoppers');
    }

    public function test_budgets_are_personal_and_the_owner_can_set_anyones(): void
    {
        ['owner' => $owner, 'editor' => $editor, 'viewer' => $viewer, 'trip' => $trip] = $this->tripFixture();

        $this->actingAs($viewer)->patchJson("/api/trips/{$trip->id}/budgets/{$viewer->id}", ['budget_rm' => 600])
            ->assertOk()->assertJsonPath('data.budget_rm', 600);
        $this->actingAs($viewer)->patchJson("/api/trips/{$trip->id}/budgets/{$editor->id}", ['budget_rm' => 1])
            ->assertForbidden();
        $this->actingAs($owner)->patchJson("/api/trips/{$trip->id}/budgets/{$editor->id}", ['budget_rm' => 1200])
            ->assertOk()->assertJsonPath('data.budget_rm', 1200);
        $this->actingAs($viewer)->patchJson("/api/trips/{$trip->id}/budgets/{$viewer->id}", ['budget_rm' => -5])
            ->assertUnprocessable();
    }

    public function test_shopping_validation(): void
    {
        ['editor' => $editor, 'trip' => $trip] = $this->tripFixture();

        $this->actingAs($editor)->postJson('/api/shopping-items', [
            'trip_id' => $trip->id,
            'name' => '',
            'qty' => 0,
            'estimated_yen' => -1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'qty', 'estimated_yen']);
    }

    public function test_everyone_including_viewers_can_add_tick_and_delete_ideas(): void
    {
        ['viewer' => $viewer, 'trip' => $trip, 'idea' => $idea, 'day' => $day] = $this->tripFixture();

        $newId = $this->actingAs($viewer)->postJson("/api/trips/{$trip->id}/ideas", [
            'name' => 'Fushimi Inari Taisha',
            'region' => 'Kansai',
            'city' => 'Kyoto',
            'area' => 'Fushimi',
        ])->assertCreated()->assertJsonPath('data.area', 'Fushimi')->json('data.id');

        $this->actingAs($viewer)->patchJson("/api/ideas/{$idea->id}", ['is_visited' => true, 'planned_day_id' => $day->id])
            ->assertOk()
            ->assertJsonPath('data.is_visited', true)
            ->assertJsonPath('data.planned_day_id', $day->id);

        $this->actingAs($viewer)->deleteJson("/api/ideas/{$newId}")->assertNoContent();
        $this->assertSoftDeleted('ideas', ['id' => $newId]);
    }

    public function test_a_deleted_idea_can_be_restored(): void
    {
        ['viewer' => $viewer, 'idea' => $idea] = $this->tripFixture();

        $this->actingAs($viewer)->deleteJson("/api/ideas/{$idea->id}")->assertNoContent();
        $this->actingAs($viewer)->patchJson("/api/ideas/{$idea->id}/restore")
            ->assertOk()->assertJsonPath('data.deleted_at', null);

        $this->assertNotSoftDeleted('ideas', ['id' => $idea->id]);
    }

    public function test_outsiders_cannot_touch_ideas(): void
    {
        ['outsider' => $outsider, 'trip' => $trip, 'idea' => $idea] = $this->tripFixture();

        $this->actingAs($outsider)->postJson("/api/trips/{$trip->id}/ideas", ['name' => 'x', 'region' => 'Tokyo'])->assertNotFound();
        $this->actingAs($outsider)->deleteJson("/api/ideas/{$idea->id}")->assertNotFound();
    }

    public function test_ideas_cannot_be_planned_on_another_trips_day(): void
    {
        ['editor' => $editor, 'idea' => $idea] = $this->tripFixture();
        $foreignDay = Day::factory()->for(Trip::factory())->create();

        $this->actingAs($editor)->patchJson("/api/ideas/{$idea->id}", ['planned_day_id' => $foreignDay->id])
            ->assertUnprocessable()->assertJsonValidationErrors('planned_day_id');
    }
}

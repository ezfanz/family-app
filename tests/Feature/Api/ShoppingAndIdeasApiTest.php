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

    public function test_editor_can_manage_shopping_items(): void
    {
        ['editor' => $editor, 'trip' => $trip] = $this->tripFixture();
        $id = (string) Str::uuid7();

        $this->actingAs($editor)->postJson('/api/shopping-items', [
            'id' => $id,
            'trip_id' => $trip->id,
            'name' => 'Tokyo Banana, 8 pcs',
            'for_whom' => 'For Mak',
            'qty' => 2,
            'estimated_yen' => 2400,
        ])->assertCreated()->assertJsonPath('data.for_whom', 'For Mak');

        $this->actingAs($editor)->patchJson("/api/shopping-items/{$id}", ['is_bought' => true, 'actual_yen' => 2380])
            ->assertOk()
            ->assertJsonPath('data.is_bought', true)
            ->assertJsonPath('data.actual_yen', 2380);

        $this->actingAs($editor)->deleteJson("/api/shopping-items/{$id}")->assertNoContent();
        $this->assertSoftDeleted('shopping_items', ['id' => $id]);
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

    public function test_viewer_cannot_change_shopping(): void
    {
        ['viewer' => $viewer, 'trip' => $trip, 'item' => $item] = $this->tripFixture();

        $this->actingAs($viewer)->postJson('/api/shopping-items', ['trip_id' => $trip->id, 'name' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->patchJson("/api/shopping-items/{$item->id}", ['is_bought' => true])->assertForbidden();
        $this->actingAs($viewer)->deleteJson("/api/shopping-items/{$item->id}")->assertForbidden();

        $this->assertFalse(ShoppingItem::find($item->id)->is_bought);
    }

    public function test_editor_can_add_tick_plan_and_delete_ideas(): void
    {
        ['editor' => $editor, 'trip' => $trip, 'idea' => $idea, 'day' => $day] = $this->tripFixture();

        $newId = $this->actingAs($editor)->postJson("/api/trips/{$trip->id}/ideas", [
            'name' => 'Fushimi Inari Taisha',
            'region' => 'Kansai',
            'city' => 'Kyoto',
            'area' => 'Fushimi',
        ])->assertCreated()->assertJsonPath('data.area', 'Fushimi')->json('data.id');

        $this->actingAs($editor)->patchJson("/api/ideas/{$idea->id}", ['is_visited' => true, 'planned_day_id' => $day->id])
            ->assertOk()
            ->assertJsonPath('data.is_visited', true)
            ->assertJsonPath('data.planned_day_id', $day->id);

        $this->actingAs($editor)->deleteJson("/api/ideas/{$newId}")->assertNoContent();
        $this->assertSoftDeleted('ideas', ['id' => $newId]);
    }

    public function test_ideas_cannot_be_planned_on_another_trips_day(): void
    {
        ['editor' => $editor, 'idea' => $idea] = $this->tripFixture();
        $foreignDay = Day::factory()->for(Trip::factory())->create();

        $this->actingAs($editor)->patchJson("/api/ideas/{$idea->id}", ['planned_day_id' => $foreignDay->id])
            ->assertUnprocessable()->assertJsonValidationErrors('planned_day_id');
    }

    public function test_viewer_cannot_change_ideas(): void
    {
        ['viewer' => $viewer, 'trip' => $trip, 'idea' => $idea] = $this->tripFixture();

        $this->actingAs($viewer)->postJson("/api/trips/{$trip->id}/ideas", ['name' => 'x', 'region' => 'Tokyo'])->assertForbidden();
        $this->actingAs($viewer)->patchJson("/api/ideas/{$idea->id}", ['is_visited' => true])->assertForbidden();
        $this->actingAs($viewer)->deleteJson("/api/ideas/{$idea->id}")->assertForbidden();
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\ShoppingItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingOverviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_the_group_by_default_and_any_member(): void
    {
        ['trip' => $trip, 'owner' => $owner, 'editor' => $editor] = $this->tripFixture();
        $trip->forceFill(['exchange_rate_auto' => false, 'exchange_rate_rm_per_100_yen' => 3])->save();
        $trip->members()->updateExistingPivot($editor->id, ['budget_rm' => 500]);
        ShoppingItem::factory()->for($trip)->create(['user_id' => $editor->id, 'actual_yen' => 10000, 'is_bought' => true, 'category' => 'beauty']);

        $this->actingAs($owner)->getJson("/api/trips/{$trip->id}/shopping-overview")
            ->assertOk()
            ->assertJsonPath('data.user_id', null)
            ->assertJsonPath('data.spent_rm', 300)
            ->assertJsonCount(3, 'data.people');

        $this->actingAs($owner)->getJson("/api/trips/{$trip->id}/shopping-overview?user={$editor->id}")
            ->assertOk()
            ->assertJsonPath('data.user_id', $editor->id)
            ->assertJsonPath('data.budget_rm', 500)
            ->assertJsonPath('data.categories.0.label', 'Beauty');
    }

    public function test_editors_and_viewers_only_get_their_own(): void
    {
        ['trip' => $trip, 'owner' => $owner, 'editor' => $editor, 'viewer' => $viewer] = $this->tripFixture();

        $this->actingAs($editor)->getJson("/api/trips/{$trip->id}/shopping-overview")
            ->assertOk()
            ->assertJsonPath('data.user_id', $editor->id)
            ->assertJsonPath('data.people', []);

        $this->actingAs($editor)->getJson("/api/trips/{$trip->id}/shopping-overview?user={$viewer->id}")->assertForbidden();
        $this->actingAs($editor)->getJson("/api/trips/{$trip->id}/shopping-overview?user={$owner->id}")->assertForbidden();
        $this->actingAs($viewer)->getJson("/api/trips/{$trip->id}/shopping-overview?user=all")->assertForbidden();
    }

    public function test_outsiders_get_not_found(): void
    {
        ['trip' => $trip, 'outsider' => $outsider] = $this->tripFixture();

        $this->actingAs($outsider)->getJson("/api/trips/{$trip->id}/shopping-overview")->assertNotFound();
    }
}

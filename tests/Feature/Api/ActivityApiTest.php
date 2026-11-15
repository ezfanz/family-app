<?php

namespace Tests\Feature\Api;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\Day;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActivityApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function stopPayload(int $dayId, array $overrides = []): array
    {
        return [
            'day_id' => $dayId,
            'time' => '15:30',
            'name' => 'Umeda Sky Building',
            'transport' => 'Midosuji Line → Umeda',
            'meal' => '',
            'ticket' => 'Klook',
            'notes' => 'Sunset 16:45.',
            'places' => ['Umeda Sky Building', '  ', 'Umeda Station'],
            'is_halal' => false,
            ...$overrides,
        ];
    }

    public function test_editor_can_create_update_and_delete_a_stop(): void
    {
        ['editor' => $editor, 'day' => $day, 'trip' => $trip] = $this->tripFixture();
        $otherDay = Day::factory()->for($trip)->create(['day_number' => 2]);

        $id = $this->actingAs($editor)->postJson('/api/activities', $this->stopPayload($day->id))
            ->assertCreated()
            ->assertJsonPath('data.places', ['Umeda Sky Building', 'Umeda Station'])
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        $this->actingAs($editor)->patchJson("/api/activities/{$id}", ['name' => 'Umeda Sky', 'day_id' => $otherDay->id, 'time' => '16:00'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Umeda Sky')
            ->assertJsonPath('data.day_id', $otherDay->id)
            ->assertJsonPath('data.time', '16:00')
            ->assertJsonPath('data.ticket', 'Klook');

        $this->actingAs($editor)->deleteJson("/api/activities/{$id}")->assertNoContent();
        $this->assertSoftDeleted('activities', ['id' => $id]);
    }

    public function test_create_with_a_client_id_is_idempotent(): void
    {
        ['editor' => $editor, 'day' => $day] = $this->tripFixture();
        $id = (string) Str::uuid7();

        $this->actingAs($editor)->postJson('/api/activities', $this->stopPayload($day->id, ['id' => $id]))->assertCreated();
        $this->actingAs($editor)->postJson('/api/activities', $this->stopPayload($day->id, ['id' => $id]))->assertOk();

        $this->assertSame(1, Activity::whereKey($id)->count());
    }

    public function test_a_stop_cannot_move_to_another_trips_day(): void
    {
        ['editor' => $editor, 'activity' => $activity] = $this->tripFixture();
        $foreignDay = Day::factory()->for(Trip::factory())->create();

        $this->actingAs($editor)->patchJson("/api/activities/{$activity->id}", ['day_id' => $foreignDay->id])
            ->assertUnprocessable()->assertJsonValidationErrors('day_id');
    }

    public function test_validation_matches_the_prototype_limits(): void
    {
        ['editor' => $editor, 'day' => $day] = $this->tripFixture();

        $this->actingAs($editor)->postJson('/api/activities', $this->stopPayload($day->id, [
            'name' => str_repeat('a', 161),
            'notes' => str_repeat('a', 1001),
            'transport' => str_repeat('a', 201),
            'ticket' => str_repeat('a', 41),
            'places' => array_fill(0, 11, 'Place'),
            'time' => '25:00',
            'meal' => 'Supper',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['name', 'notes', 'transport', 'ticket', 'places', 'time', 'meal']);
    }

    public function test_viewer_cannot_create_edit_or_delete_stops(): void
    {
        ['viewer' => $viewer, 'day' => $day, 'activity' => $activity] = $this->tripFixture();

        $this->actingAs($viewer)->postJson('/api/activities', $this->stopPayload($day->id))->assertForbidden();
        $this->actingAs($viewer)->patchJson("/api/activities/{$activity->id}", ['name' => 'Hacked'])->assertForbidden();
        $this->actingAs($viewer)->deleteJson("/api/activities/{$activity->id}")->assertForbidden();

        $this->assertSame('Kuromon Market', $activity->fresh()->name);
    }

    public function test_viewer_can_tick_a_stop_and_keep_a_private_note(): void
    {
        ['viewer' => $viewer, 'editor' => $editor, 'trip' => $trip, 'activity' => $activity] = $this->tripFixture();

        $this->actingAs($viewer)->patchJson("/api/activities/{$activity->id}/status", ['status' => 'done'])
            ->assertOk()->assertJsonPath('data.status', 'done');
        $this->assertSame(ActivityStatus::Done, $activity->fresh()->status);

        $this->actingAs($viewer)->putJson("/api/activities/{$activity->id}/note", ['note' => 'sedap gila'])
            ->assertOk()->assertJsonPath('data.note', 'sedap gila');

        $this->actingAs($editor)->getJson("/api/trips/{$trip->id}")->assertJsonCount(0, 'my_notes');
        $this->actingAs($viewer)->getJson("/api/trips/{$trip->id}")->assertJsonPath('my_notes.0.note', 'sedap gila');
    }

    public function test_outsiders_cannot_touch_stops(): void
    {
        ['outsider' => $outsider, 'activity' => $activity, 'day' => $day] = $this->tripFixture();

        $this->actingAs($outsider)->patchJson("/api/activities/{$activity->id}/status", ['status' => 'done'])->assertNotFound();
        $this->actingAs($outsider)->putJson("/api/activities/{$activity->id}/note", ['note' => 'x'])->assertNotFound();
        $this->actingAs($outsider)->postJson('/api/activities', $this->stopPayload($day->id))->assertNotFound();
    }

    public function test_status_must_be_a_known_value(): void
    {
        ['editor' => $editor, 'activity' => $activity] = $this->tripFixture();

        $this->actingAs($editor)->patchJson("/api/activities/{$activity->id}/status", ['status' => 'skip'])
            ->assertUnprocessable();
    }
}

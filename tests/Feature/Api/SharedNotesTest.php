<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_owners_see_everyone_elses_notes_with_who_wrote_them(): void
    {
        ['owner' => $owner, 'editor' => $editor, 'viewer' => $viewer, 'trip' => $trip, 'activity' => $stop] = $this->tripFixture();
        $this->actingAs($viewer)->putJson("/api/activities/{$stop->id}/note", ['note' => 'Bring cash for the lockers'])->assertOk();
        $this->actingAs($owner)->putJson("/api/activities/{$stop->id}/note", ['note' => 'Meet at exit 3'])->assertOk();

        $this->actingAs($owner)->getJson("/api/trips/{$trip->id}")
            ->assertOk()
            ->assertJsonPath('my_notes.0.note', 'Meet at exit 3')
            ->assertJsonCount(1, 'others_notes')
            ->assertJsonPath('others_notes.0.activity_id', $stop->id)
            ->assertJsonPath('others_notes.0.user_id', $viewer->id)
            ->assertJsonPath('others_notes.0.note', 'Bring cash for the lockers');

        // Editors and viewers still see only their own note.
        $this->actingAs($editor)->getJson("/api/trips/{$trip->id}")->assertJsonCount(0, 'others_notes');
        $this->actingAs($viewer)->getJson("/api/trips/{$trip->id}")
            ->assertJsonCount(0, 'others_notes')
            ->assertJsonPath('my_notes.0.note', 'Bring cash for the lockers');
    }

    public function test_owners_hear_about_new_and_cleared_notes_in_the_changes_feed(): void
    {
        ['owner' => $owner, 'viewer' => $viewer, 'editor' => $editor, 'trip' => $trip, 'activity' => $stop] = $this->tripFixture();
        $since = urlencode(now()->subSecond()->toIso8601String());

        $this->actingAs($viewer)->putJson("/api/activities/{$stop->id}/note", ['note' => 'Halal ramen nearby'])->assertOk();
        $this->actingAs($owner)->getJson("/api/trips/{$trip->id}/changes?since={$since}")
            ->assertJsonPath('others_notes.0.note', 'Halal ramen nearby')
            ->assertJsonPath('others_notes.0.user_id', $viewer->id);
        $this->actingAs($editor)->getJson("/api/trips/{$trip->id}/changes?since={$since}")
            ->assertJsonCount(0, 'others_notes');

        $this->actingAs($viewer)->putJson("/api/activities/{$stop->id}/note", ['note' => ''])->assertOk();
        $this->actingAs($owner)->getJson("/api/trips/{$trip->id}/changes?since={$since}")
            ->assertJsonPath('others_notes.0.note', '');
    }
}

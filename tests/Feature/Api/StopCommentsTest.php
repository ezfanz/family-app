<?php

namespace Tests\Feature\Api;

use App\Models\StopComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StopCommentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_comment_that_mentions_someone_notifies_only_them(): void
    {
        ['owner' => $owner, 'editor' => $editor, 'viewer' => $viewer, 'outsider' => $outsider, 'activity' => $stop, 'trip' => $trip] = $this->tripFixture();
        $id = (string) Str::uuid7();

        $this->actingAs($owner)->postJson("/api/activities/{$stop->id}/comments", [
            'id' => $id,
            'body' => '@Amira can you check the halal stalls?',
            'mention_ids' => [$editor->id, $owner->id, $outsider->id],
        ])->assertCreated()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.user_id', $owner->id)
            ->assertJsonPath('data.mention_ids', [$editor->id]);

        $this->assertCount(1, $editor->notifications);
        $data = $editor->notifications->first()->data;
        $this->assertSame($owner->name, $data['actor_name']);
        $this->assertSame('Kuromon Market', $data['stop_name']);
        $this->assertSame(1, $data['day_number']);
        $this->assertSame('Osaka', $data['city']);
        $this->assertSame('09:00', $data['time']);
        $this->assertSame($trip->id, $data['trip_id']);
        $this->assertCount(0, $owner->notifications, 'not for mentioning yourself');
        $this->assertCount(0, $outsider->notifications, 'not for people outside the trip');
        $this->assertCount(0, $viewer->notifications);

        // A retry from the offline queue does not post or notify twice.
        $this->actingAs($owner)->postJson("/api/activities/{$stop->id}/comments", ['id' => $id, 'body' => '@Amira can you check the halal stalls?', 'mention_ids' => [$editor->id]])
            ->assertOk();
        $this->assertSame(1, StopComment::count());
        $this->assertCount(1, $editor->fresh()->notifications);
    }

    public function test_owners_read_every_comment_others_read_theirs_and_mentions(): void
    {
        ['owner' => $owner, 'editor' => $editor, 'viewer' => $viewer, 'activity' => $stop, 'trip' => $trip] = $this->tripFixture();
        $this->actingAs($viewer)->postJson("/api/activities/{$stop->id}/comments", ['body' => 'Bring cash'])->assertCreated();
        $this->actingAs($owner)->postJson("/api/activities/{$stop->id}/comments", ['body' => '@Aisyah ok', 'mention_ids' => [$viewer->id]])->assertCreated();
        $this->actingAs($owner)->postJson("/api/activities/{$stop->id}/comments", ['body' => 'Owners only chat'])->assertCreated();

        $this->actingAs($owner)->getJson("/api/trips/{$trip->id}")->assertJsonCount(3, 'comments');
        $this->actingAs($viewer)->getJson("/api/trips/{$trip->id}")->assertJsonCount(2, 'comments');
        $this->actingAs($editor)->getJson("/api/trips/{$trip->id}")->assertJsonCount(0, 'comments');

        // Everyone gets the member list, so anyone can @mention anyone.
        $this->actingAs($editor)->getJson("/api/trips/{$trip->id}")->assertJsonCount(3, 'members');
    }

    public function test_people_delete_only_their_own_comments_and_phones_hear_about_it(): void
    {
        ['owner' => $owner, 'viewer' => $viewer, 'activity' => $stop, 'trip' => $trip] = $this->tripFixture();
        $id = $this->actingAs($viewer)->postJson("/api/activities/{$stop->id}/comments", ['body' => 'Bring cash'])->json('data.id');
        $since = urlencode(now()->subSecond()->toIso8601String());

        $this->actingAs($owner)->deleteJson("/api/comments/{$id}")->assertForbidden();
        $this->actingAs($viewer)->deleteJson("/api/comments/{$id}")->assertNoContent();
        $this->assertSoftDeleted('stop_comments', ['id' => $id]);

        $this->actingAs($owner)->getJson("/api/trips/{$trip->id}/changes?since={$since}")
            ->assertJsonPath('comments.0.id', $id)
            ->assertJsonPath('comments.0.deleted_at', fn ($value) => $value !== null);
    }

    public function test_outsiders_cannot_comment_and_empty_comments_are_refused(): void
    {
        ['outsider' => $outsider, 'viewer' => $viewer, 'activity' => $stop] = $this->tripFixture();

        $this->actingAs($outsider)->postJson("/api/activities/{$stop->id}/comments", ['body' => 'hi'])->assertNotFound();
        $this->actingAs($viewer)->postJson("/api/activities/{$stop->id}/comments", ['body' => '   '])->assertUnprocessable();
        $this->actingAs($viewer)->postJson("/api/activities/{$stop->id}/comments", ['body' => str_repeat('a', 1001)])->assertUnprocessable();
    }

    public function test_the_bell_lists_this_trips_mentions_and_marks_them_read(): void
    {
        ['owner' => $owner, 'editor' => $editor, 'activity' => $stop, 'trip' => $trip] = $this->tripFixture();
        $this->actingAs($owner)->postJson("/api/activities/{$stop->id}/comments", ['body' => '@Amira look', 'mention_ids' => [$editor->id]]);
        $this->travel(1)->seconds();
        $this->actingAs($owner)->postJson("/api/activities/{$stop->id}/comments", ['body' => '@Amira and this', 'mention_ids' => [$editor->id]]);

        $this->actingAs($editor)->getJson("/api/trips/{$trip->id}/notifications")
            ->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.excerpt', '@Amira and this')
            ->assertJsonPath('data.0.read', false);

        // The changes check carries the unread count for the bell.
        $since = urlencode(now()->subMinute()->toIso8601String());
        $this->actingAs($editor)->getJson("/api/trips/{$trip->id}/changes?since={$since}")->assertJsonPath('unread_notifications', 2);

        $this->actingAs($editor)->postJson("/api/trips/{$trip->id}/notifications/read")->assertOk()->assertJsonPath('unread_count', 0);
        $this->actingAs($editor)->getJson("/api/trips/{$trip->id}/notifications")->assertJsonPath('data.0.read', true);
        $this->actingAs($owner)->getJson("/api/trips/{$trip->id}/notifications")->assertJsonPath('unread_count', 0);
    }
}

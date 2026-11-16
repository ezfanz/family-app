<?php

namespace Tests\Feature\Api;

use App\Enums\ReceiptStage;
use App\Models\Activity;
use App\Models\Day;
use App\Models\Receipt;
use App\Models\ShoppingItem;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReceiptApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_save_their_own_receipt_and_retries_do_not_duplicate(): void
    {
        ['trip' => $trip, 'viewer' => $viewer] = $this->tripFixture();
        $payload = [
            'id' => (string) Str::uuid7(),
            'trip_id' => $trip->id,
            'store_name' => '  Matsumoto Kiyoshi ',
            'branch' => 'Dotonbori',
            'purchased_at' => '2026-11-22T19:48:00+09:00',
            'pre_tax_yen' => 3200,
        ];

        $this->actingAs($viewer)->postJson('/api/receipts', $payload)
            ->assertCreated()
            ->assertJsonPath('data.user_id', $viewer->id)
            ->assertJsonPath('data.store_name', 'Matsumoto Kiyoshi')
            ->assertJsonPath('data.stage', 'checked')
            ->assertJsonPath('data.purchased_at', '2026-11-22T10:48:00+00:00');

        $this->actingAs($viewer)->postJson('/api/receipts', $payload)->assertOk();
        $this->assertSame(1, Receipt::count());
    }

    public function test_a_receipt_without_an_amount_is_saved_as_captured(): void
    {
        ['trip' => $trip, 'viewer' => $viewer] = $this->tripFixture();

        $id = $this->actingAs($viewer)->postJson('/api/receipts', ['trip_id' => $trip->id, 'purchased_at' => now()->toIso8601String()])
            ->assertCreated()
            ->assertJsonPath('data.stage', 'captured')
            ->json('data.id');

        $this->actingAs($viewer)->patchJson("/api/receipts/{$id}", ['store_name' => 'UNIQLO', 'pre_tax_yen' => 11960])
            ->assertOk()
            ->assertJsonPath('data.stage', 'checked');
    }

    public function test_an_editor_cannot_load_or_change_another_members_receipts(): void
    {
        ['trip' => $trip, 'editor' => $editor, 'viewer' => $viewer] = $this->tripFixture();
        $theirs = Receipt::factory()->for($trip)->create(['user_id' => $viewer->id]);
        Receipt::factory()->for($trip)->create(['user_id' => $editor->id]);

        $this->actingAs($editor)->getJson("/api/trips/{$trip->id}/receipts")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $editor->id);
        $this->actingAs($editor)->getJson("/api/trips/{$trip->id}/receipts?user={$viewer->id}")->assertNotFound();
        $this->actingAs($editor)->getJson("/api/trips/{$trip->id}")->assertJsonCount(1, 'receipts');
        $this->actingAs($editor)->getJson("/api/trips/{$trip->id}/changes?since=2026-01-01T00:00:00Z")->assertJsonCount(1, 'receipts');

        $this->actingAs($editor)->patchJson("/api/receipts/{$theirs->id}", ['pre_tax_yen' => 1])->assertNotFound();
        $this->actingAs($editor)->deleteJson("/api/receipts/{$theirs->id}")->assertNotFound();
        $this->actingAs($editor)->getJson("/api/receipts/{$theirs->id}/photos/1")->assertNotFound();
        $this->actingAs($editor)->postJson('/api/receipts', ['trip_id' => $trip->id, 'user_id' => $viewer->id, 'purchased_at' => now()->toIso8601String()])->assertNotFound();
    }

    public function test_the_trip_owner_sees_and_manages_everyones_receipts(): void
    {
        ['trip' => $trip, 'owner' => $owner, 'editor' => $editor, 'viewer' => $viewer] = $this->tripFixture();
        Receipt::factory()->for($trip)->create(['user_id' => $editor->id]);
        $viewers = Receipt::factory()->for($trip)->create(['user_id' => $viewer->id]);

        $this->actingAs($owner)->getJson("/api/trips/{$trip->id}/receipts")->assertJsonCount(2, 'data');
        $this->actingAs($owner)->getJson("/api/trips/{$trip->id}/receipts?user={$viewer->id}")->assertJsonCount(1, 'data');
        $this->actingAs($owner)->patchJson("/api/receipts/{$viewers->id}", ['stage' => 'refunded', 'refund_received_yen' => 500])
            ->assertOk()
            ->assertJsonPath('data.stage', 'refunded')
            ->assertJsonPath('data.refund_received_yen', 500);
    }

    public function test_outsiders_get_not_found(): void
    {
        ['trip' => $trip, 'outsider' => $outsider, 'viewer' => $viewer] = $this->tripFixture();
        $receipt = Receipt::factory()->for($trip)->create(['user_id' => $viewer->id]);

        $this->actingAs($outsider)->getJson("/api/trips/{$trip->id}/receipts")->assertNotFound();
        $this->actingAs($outsider)->patchJson("/api/receipts/{$receipt->id}", ['pre_tax_yen' => 1])->assertNotFound();
    }

    public function test_stage_dates_follow_the_stage(): void
    {
        ['trip' => $trip, 'viewer' => $viewer] = $this->tripFixture();
        $receipt = Receipt::factory()->for($trip)->create(['user_id' => $viewer->id, 'pre_tax_yen' => 6000]);

        $this->actingAs($viewer)->patchJson("/api/receipts/{$receipt->id}", ['stage' => 'refunded'])->assertOk();
        $receipt->refresh();
        $this->assertNotNull($receipt->customs_confirmed_at);
        $this->assertNotNull($receipt->refunded_at);

        $this->actingAs($viewer)->patchJson("/api/receipts/{$receipt->id}", ['stage' => 'checked'])->assertOk();
        $receipt->refresh();
        $this->assertNull($receipt->customs_confirmed_at);
        $this->assertNull($receipt->refunded_at);
    }

    public function test_customs_done_confirms_only_my_eligible_receipts(): void
    {
        ['trip' => $trip, 'viewer' => $viewer, 'editor' => $editor] = $this->tripFixture();
        $day = ['purchased_at' => '2026-11-22 10:00:00'];
        $muji = Receipt::factory()->for($trip)->create([...$day, 'user_id' => $viewer->id, 'store_name' => 'MUJI', 'branch' => 'Namba', 'pre_tax_yen' => 5990]);
        $small = Receipt::factory()->for($trip)->create([...$day, 'user_id' => $viewer->id, 'store_name' => '7-Eleven', 'branch' => 'Namba', 'pre_tax_yen' => 1800]);
        $refunded = Receipt::factory()->for($trip)->create([...$day, 'user_id' => $viewer->id, 'store_name' => 'UNIQLO', 'branch' => 'Namba', 'pre_tax_yen' => 9000, 'stage' => ReceiptStage::Refunded]);
        $someoneElses = Receipt::factory()->for($trip)->create([...$day, 'user_id' => $editor->id, 'store_name' => 'MUJI', 'pre_tax_yen' => 9000]);

        $this->actingAs($viewer)->postJson("/api/trips/{$trip->id}/customs-done")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $muji->id);

        $this->assertSame(ReceiptStage::CustomsConfirmed, $muji->fresh()->stage);
        $this->assertSame(ReceiptStage::Checked, $small->fresh()->stage);
        $this->assertSame(ReceiptStage::Refunded, $refunded->fresh()->stage);
        $this->assertSame(ReceiptStage::Checked, $someoneElses->fresh()->stage);
    }

    public function test_photos_are_private_and_only_streamed_to_allowed_people(): void
    {
        Storage::fake('receipts');
        ['trip' => $trip, 'owner' => $owner, 'editor' => $editor, 'viewer' => $viewer] = $this->tripFixture();
        $receipt = Receipt::factory()->for($trip)->create(['user_id' => $viewer->id]);

        $this->actingAs($viewer)->post("/api/receipts/{$receipt->id}/photos", [
            'part' => 1,
            'photo' => UploadedFile::fake()->image('receipt.jpg', 600, 1200),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.photo_parts', [1]);

        $path = $receipt->photos()->sole()->path;
        Storage::disk('receipts')->assertExists($path);
        $this->assertStringStartsWith("trips/{$trip->id}/{$receipt->id}/1-", $path);

        $this->actingAs($viewer)->get("/api/receipts/{$receipt->id}/photos/1")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($owner)->get("/api/receipts/{$receipt->id}/photos/1")->assertOk();
        $this->actingAs($editor)->getJson("/api/receipts/{$receipt->id}/photos/1")->assertNotFound();

        // Replacing part 1 removes the old file.
        $this->actingAs($viewer)->post("/api/receipts/{$receipt->id}/photos", [
            'part' => 1,
            'photo' => UploadedFile::fake()->image('again.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('receipts')->assertMissing($path);
        $this->assertSame(1, $receipt->photos()->count());
    }

    public function test_photos_must_be_images(): void
    {
        Storage::fake('receipts');
        ['trip' => $trip, 'viewer' => $viewer] = $this->tripFixture();
        $receipt = Receipt::factory()->for($trip)->create(['user_id' => $viewer->id]);

        $this->actingAs($viewer)->post("/api/receipts/{$receipt->id}/photos", [
            'part' => 1,
            'photo' => UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('photo');
    }

    public function test_linking_one_shopping_item_fills_its_paid_amount(): void
    {
        ['trip' => $trip, 'editor' => $editor, 'item' => $item] = $this->tripFixture();
        $other = ShoppingItem::factory()->for($trip)->create(['user_id' => User::factory()]);

        $this->actingAs($editor)->postJson('/api/receipts', [
            'trip_id' => $trip->id,
            'store_name' => 'Royce',
            'purchased_at' => '2026-11-22T12:00:00+09:00',
            'pre_tax_yen' => 5000,
            'tax_rate' => 8,
            'shopping_item_ids' => [$item->id],
        ])->assertCreated()->assertJsonPath('data.shopping_item_ids', [$item->id]);

        $item->refresh();
        $this->assertTrue($item->is_bought);
        $this->assertSame(5400, $item->actual_yen);

        $this->actingAs($editor)->postJson('/api/receipts', [
            'trip_id' => $trip->id,
            'purchased_at' => '2026-11-22T12:00:00+09:00',
            'shopping_item_ids' => [$other->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('shopping_item_ids');
    }

    public function test_airport_checklist_is_saved_per_person(): void
    {
        ['trip' => $trip, 'viewer' => $viewer, 'editor' => $editor] = $this->tripFixture();

        $this->actingAs($viewer)->putJson("/api/trips/{$trip->id}/airport-checklist", ['checked' => ['passport', 'hand_luggage']])
            ->assertOk()
            ->assertJsonPath('checked', ['passport', 'hand_luggage']);

        $this->actingAs($viewer)->getJson("/api/trips/{$trip->id}")->assertJsonPath('my_airport_checklist', ['passport', 'hand_luggage']);
        $this->actingAs($editor)->getJson("/api/trips/{$trip->id}")->assertJsonPath('my_airport_checklist', []);
    }

    public function test_new_trips_get_a_tax_refund_stop_on_the_last_day_once(): void
    {
        $user = User::factory()->create();
        $trip = Trip::startFor($user, ['name' => 'Japan', 'start_date' => '2026-11-21', 'end_date' => '2026-12-04']);

        $stop = Activity::where('system_key', 'tax_refund')->sole();
        $this->assertSame($trip->days()->reorder('day_number', 'desc')->first()->id, $stop->day_id);
        $this->assertSame('09:00', $stop->timeLabel());

        $stop->delete();
        $trip->ensureTaxRefundStop();
        $this->assertSame(0, Activity::where('system_key', 'tax_refund')->count());
    }

    public function test_the_tax_refund_stop_is_three_hours_before_a_flight(): void
    {
        $trip = Trip::factory()->create();
        $last = Day::factory()->for($trip)->create(['day_number' => 2]);
        Day::factory()->for($trip)->create(['day_number' => 1]);
        Activity::factory()->create(['day_id' => $last->id, 'trip_id' => $trip->id, 'start_time' => '21:30', 'name' => 'Flight home from HND']);

        $trip->ensureTaxRefundStop();

        $this->assertSame('18:30', Activity::where('system_key', 'tax_refund')->sole()->timeLabel());
    }
}

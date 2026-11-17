<?php

namespace Tests\Feature\Api;

use App\Models\Receipt;
use App\Models\User;
use App\Services\TaxRefund\ReceiptReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ReceiptReadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A reader that counts its calls and returns a fixed reading.
     */
    private function fakeReader(?callable $read = null): object
    {
        $reader = new class($read) implements ReceiptReader
        {
            public int $calls = 0;

            /** @var list<array{data: string, mime: string}> */
            public array $images = [];

            public function __construct(private $read) {}

            public function read(array $images): array
            {
                $this->calls++;
                $this->images = $images;

                return $this->read ? ($this->read)() : [
                    'fields' => ['store_name' => 'Don Quijote', 'branch' => 'Dotonbori', 'date' => '2026-11-22', 'time' => '19:42', 'amount_yen' => 8240, 'amount_kind' => 'pre_tax', 'tax_rate' => 10],
                    'confidence' => ['store_name' => 0.98, 'branch' => 0.9, 'date' => 0.95, 'time' => 0.9, 'amount_yen' => 0.62, 'tax_rate' => 0.9],
                    'model' => 'claude-haiku-5-5',
                ];
            }
        };
        $this->app->instance(ReceiptReader::class, $reader);

        return $reader;
    }

    /**
     * @return array{receipt: Receipt, viewer: User, editor: User, owner: User}
     */
    private function receiptWithPhotos(int $parts = 1): array
    {
        Storage::fake('receipts');
        ['trip' => $trip, 'viewer' => $viewer, 'editor' => $editor, 'owner' => $owner] = $this->tripFixture();
        $receipt = Receipt::factory()->for($trip)->create(['user_id' => $viewer->id]);
        foreach (range(1, $parts) as $part) {
            $this->actingAs($viewer)->post("/api/receipts/{$receipt->id}/photos", [
                'part' => $part,
                'photo' => UploadedFile::fake()->image("receipt-{$part}.jpg", 600, 1200),
            ], ['Accept' => 'application/json'])->assertOk();
        }

        return ['receipt' => $receipt, 'viewer' => $viewer, 'editor' => $editor, 'owner' => $owner];
    }

    public function test_it_returns_the_reading_for_both_parts_and_caches_it(): void
    {
        $reader = $this->fakeReader();
        ['receipt' => $receipt, 'viewer' => $viewer] = $this->receiptWithPhotos(parts: 2);

        $this->actingAs($viewer)->postJson("/api/receipts/{$receipt->id}/read")
            ->assertOk()
            ->assertJsonPath('data.fields.store_name', 'Don Quijote')
            ->assertJsonPath('data.fields.amount_yen', 8240)
            ->assertJsonPath('data.confidence.amount_yen', 0.62);

        $this->assertCount(2, $reader->images);
        $this->assertSame('image/jpeg', $reader->images[0]['mime']);
        $this->assertNotEmpty($reader->images[1]['data']);

        $this->actingAs($viewer)->postJson("/api/receipts/{$receipt->id}/read")->assertOk();
        $this->assertSame(1, $reader->calls);
    }

    public function test_a_new_photo_is_read_again(): void
    {
        $reader = $this->fakeReader();
        ['receipt' => $receipt, 'viewer' => $viewer] = $this->receiptWithPhotos();

        $this->actingAs($viewer)->postJson("/api/receipts/{$receipt->id}/read")->assertOk();
        $this->actingAs($viewer)->post("/api/receipts/{$receipt->id}/photos", [
            'part' => 1,
            'photo' => UploadedFile::fake()->image('retake.jpg', 600, 1200),
        ], ['Accept' => 'application/json'])->assertOk();
        $this->actingAs($viewer)->postJson("/api/receipts/{$receipt->id}/read")->assertOk();

        $this->assertSame(2, $reader->calls);
    }

    public function test_only_people_who_manage_the_receipt_can_read_it(): void
    {
        $reader = $this->fakeReader();
        ['receipt' => $receipt, 'editor' => $editor, 'owner' => $owner] = $this->receiptWithPhotos();

        $this->actingAs($editor)->postJson("/api/receipts/{$receipt->id}/read")->assertNotFound();
        $this->assertSame(0, $reader->calls);

        $this->actingAs($owner)->postJson("/api/receipts/{$receipt->id}/read")->assertOk();
    }

    public function test_a_receipt_without_a_photo_cannot_be_read(): void
    {
        $reader = $this->fakeReader();
        ['trip' => $trip, 'viewer' => $viewer] = $this->tripFixture();
        $receipt = Receipt::factory()->for($trip)->create(['user_id' => $viewer->id]);

        $this->actingAs($viewer)->postJson("/api/receipts/{$receipt->id}/read")->assertUnprocessable();
        $this->assertSame(0, $reader->calls);
    }

    public function test_a_failed_reading_says_so_and_is_not_cached(): void
    {
        $reader = $this->fakeReader(fn () => throw new RuntimeException('overloaded'));
        ['receipt' => $receipt, 'viewer' => $viewer] = $this->receiptWithPhotos();

        $this->actingAs($viewer)->postJson("/api/receipts/{$receipt->id}/read")
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Couldn’t read this receipt. Type the details in.');
        $this->actingAs($viewer)->postJson("/api/receipts/{$receipt->id}/read")->assertServiceUnavailable();

        $this->assertSame(2, $reader->calls);
    }

    public function test_the_app_is_told_whether_automatic_reading_is_on(): void
    {
        ['trip' => $trip, 'viewer' => $viewer] = $this->tripFixture();

        $this->actingAs($viewer)->getJson("/api/trips/{$trip->id}")->assertJsonPath('tax_refund.auto_read', false);

        config(['services.anthropic.key' => 'test-key']);
        $this->actingAs($viewer)->getJson("/api/trips/{$trip->id}")->assertJsonPath('tax_refund.auto_read', true);

        config(['tax_refund.reader' => 'manual']);
        $this->actingAs($viewer)->getJson("/api/trips/{$trip->id}")->assertJsonPath('tax_refund.auto_read', false);
    }
}

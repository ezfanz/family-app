<?php

namespace Tests\Unit;

use Anthropic\Client;
use App\Services\TaxRefund\ClaudeReceiptReader;
use Tests\TestCase;

class ClaudeReceiptReaderTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function raw(array $overrides = []): array
    {
        return array_replace([
            'is_receipt' => true,
            'store_name' => ' Don Quijote ',
            'branch' => 'Dotonbori',
            'date' => '2026-11-22',
            'time' => '19:42',
            'amount_yen' => 8240,
            'amount_kind' => 'pre_tax',
            'tax_rate' => 10,
            'confidence' => ['store_name' => 0.98, 'branch' => 0.9, 'date' => 0.95, 'time' => 0.9, 'amount_yen' => 0.92, 'tax_rate' => 1.4],
        ], $overrides);
    }

    private function reader(): ClaudeReceiptReader
    {
        return new ClaudeReceiptReader(new Client(apiKey: 'unused'), 'claude-haiku-5-5', 'claude-sonnet-5-5', 0.75);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-23 10:00:00');
    }

    public function test_a_clear_reading_is_cleaned_up(): void
    {
        $result = ClaudeReceiptReader::normalize($this->raw(), 'claude-haiku-5-5');

        $this->assertSame([
            'store_name' => 'Don Quijote',
            'branch' => 'Dotonbori',
            'date' => '2026-11-22',
            'time' => '19:42',
            'amount_yen' => 8240,
            'amount_kind' => 'pre_tax',
            'tax_rate' => 10,
        ], $result['fields']);
        $this->assertSame(1.0, $result['confidence']['tax_rate']);
        $this->assertSame('claude-haiku-5-5', $result['model']);
    }

    public function test_empty_and_implausible_values_are_dropped(): void
    {
        $result = ClaudeReceiptReader::normalize($this->raw([
            'branch' => '  ',
            'date' => '2026-02-30',
            'time' => '25:10',
            'amount_yen' => 0,
            'amount_kind' => 'none',
            'tax_rate' => 5,
        ]));

        $this->assertSame(['store_name' => 'Don Quijote'], $result['fields']);
        $this->assertSame(['store_name' => 0.98], $result['confidence']);

        $this->assertArrayNotHasKey('date', ClaudeReceiptReader::normalize($this->raw(['date' => '2026-12-25']))['fields'], 'future date');
        $this->assertArrayNotHasKey('date', ClaudeReceiptReader::normalize($this->raw(['date' => '2019-05-01']))['fields'], 'years old');
    }

    public function test_items_and_the_english_summary_are_cleaned_up(): void
    {
        $result = ClaudeReceiptReader::normalize($this->raw([
            'summary' => '  Tax-free shopping at Don Quijote Dotonbori.  ',
            'items' => [
                ['name_en' => 'KitKat Matcha', 'name_ja' => '※キットカット抹茶', 'qty' => 1, 'price_yen' => 1280, 'reduced_rate' => true],
                ['name_en' => '', 'name_ja' => '', 'qty' => 1, 'price_yen' => 100, 'reduced_rate' => false],
                ['name_en' => 'Shiseido face cream', 'name_ja' => '資生堂 クリーム', 'qty' => 0, 'price_yen' => -5, 'reduced_rate' => false],
                'not an item',
            ],
        ]));

        $this->assertSame('Tax-free shopping at Don Quijote Dotonbori.', $result['summary']);
        $this->assertSame([
            ['name_en' => 'KitKat Matcha', 'name_ja' => '※キットカット抹茶', 'qty' => 1, 'price_yen' => 1280, 'reduced_rate' => true],
            ['name_en' => 'Shiseido face cream', 'name_ja' => '資生堂 クリーム', 'qty' => 1, 'price_yen' => 0, 'reduced_rate' => false],
        ], $result['items']);

        $merged = ClaudeReceiptReader::merge($result, ClaudeReceiptReader::normalize($this->raw(['items' => [], 'summary' => ''])));
        $this->assertCount(2, $merged['items'], 'keeps the list when the second look found none');
        $this->assertSame('Tax-free shopping at Don Quijote Dotonbori.', $merged['summary']);
    }

    public function test_the_tax_rate_comes_from_the_items_when_they_say_more(): void
    {
        $mixed = [
            ['name_en' => 'KitKat Matcha', 'name_ja' => '※キットカット抹茶', 'qty' => 1, 'price_yen' => 1280, 'reduced_rate' => true],
            ['name_en' => 'Tokyo Banana', 'name_ja' => '※東京ばな奈', 'qty' => 1, 'price_yen' => 2160, 'reduced_rate' => true],
            ['name_en' => 'Shiseido face cream', 'name_ja' => '資生堂 クリーム', 'qty' => 1, 'price_yen' => 4800, 'reduced_rate' => false],
            ['name_en' => 'Pokemon plush toy', 'name_ja' => 'ポケモン ぬいぐるみ', 'qty' => 1, 'price_yen' => 2980, 'reduced_rate' => false],
        ];

        // Mixed: the larger share (¥7,780 at 10% vs ¥3,440 at 8%) wins, still flagged to check.
        $result = ClaudeReceiptReader::rateFromItems(ClaudeReceiptReader::normalize($this->raw(['tax_rate' => 8, 'items' => $mixed])));
        $this->assertSame(10, $result['fields']['tax_rate']);
        $this->assertSame(0.6, $result['confidence']['tax_rate']);
        $this->assertFalse($this->reader()->needsSecondLook($result), 'reading again cannot unmix a mixed receipt');

        // All food: clearly 8%.
        $food = array_slice($mixed, 0, 2);
        $result = ClaudeReceiptReader::rateFromItems(ClaudeReceiptReader::normalize($this->raw(['tax_rate' => 10, 'items' => $food, 'confidence' => ['tax_rate' => 0.5] + $this->raw()['confidence']])));
        $this->assertSame(8, $result['fields']['tax_rate']);
        $this->assertSame(0.9, $result['confidence']['tax_rate']);

        // No prices to go on: keep what the model said.
        $result = ClaudeReceiptReader::rateFromItems(ClaudeReceiptReader::normalize($this->raw(['tax_rate' => 10])));
        $this->assertSame(10, $result['fields']['tax_rate']);
        $this->assertSame(1.0, $result['confidence']['tax_rate']);
    }

    public function test_a_photo_that_is_not_a_receipt_gives_nothing(): void
    {
        $this->assertSame([], ClaudeReceiptReader::normalize($this->raw(['is_receipt' => false]))['fields']);
    }

    public function test_an_unsure_store_date_or_amount_gets_a_second_look(): void
    {
        $reader = $this->reader();

        $this->assertFalse($reader->needsSecondLook(ClaudeReceiptReader::normalize($this->raw())));
        $this->assertTrue($reader->needsSecondLook(ClaudeReceiptReader::normalize($this->raw(['confidence' => ['amount_yen' => 0.5] + $this->raw()['confidence']]))));
        $this->assertTrue($reader->needsSecondLook(ClaudeReceiptReader::normalize($this->raw(['date' => '']))));

        // 8% or 10% changes the refund (mixed receipts are common), so it counts too.
        $this->assertTrue($reader->needsSecondLook(ClaudeReceiptReader::normalize($this->raw(['tax_rate' => 8, 'confidence' => ['tax_rate' => 0.6] + $this->raw()['confidence']]))));

        // An unsure branch alone is not worth a second, dearer reading.
        $this->assertFalse($reader->needsSecondLook(ClaudeReceiptReader::normalize($this->raw(['confidence' => ['branch' => 0.2] + $this->raw()['confidence']]))));
    }

    public function test_the_more_confident_reading_wins_per_field(): void
    {
        $quick = ClaudeReceiptReader::normalize($this->raw([
            'amount_yen' => 8420,
            'amount_kind' => 'total',
            'confidence' => ['amount_yen' => 0.4] + $this->raw()['confidence'],
        ]), 'claude-haiku-5-5');
        $careful = ClaudeReceiptReader::normalize($this->raw([
            'store_name' => 'Donki',
            'confidence' => ['store_name' => 0.7, 'amount_yen' => 0.96] + $this->raw()['confidence'],
        ]), 'claude-sonnet-5-5');

        $merged = ClaudeReceiptReader::merge($quick, $careful);

        $this->assertSame('Don Quijote', $merged['fields']['store_name']);
        $this->assertSame(8240, $merged['fields']['amount_yen']);
        $this->assertSame('pre_tax', $merged['fields']['amount_kind']);
        $this->assertSame(0.96, $merged['confidence']['amount_yen']);
        $this->assertSame('claude-sonnet-5-5', $merged['model']);
    }
}

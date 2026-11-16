<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    private function bnmRate(string $date, float $middle): array
    {
        return [
            'data' => ['currency_code' => 'JPY', 'unit' => 100, 'rate' => ['date' => $date, 'buying_rate' => $middle - 0.002, 'selling_rate' => $middle + 0.002, 'middle_rate' => $middle]],
            'meta' => ['quote' => 'rm', 'session' => '0900'],
        ];
    }

    public function test_it_uses_bank_negara_malaysia_and_caches_the_rate(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.bnm.gov.my/public/exchange-rate/JPY?session=1700*' => Http::response(['message' => 'No records found.', 'code' => 404], 404),
            'api.bnm.gov.my/public/exchange-rate/JPY?session=1200*' => Http::response(['message' => 'No records found.', 'code' => 404], 404),
            'api.bnm.gov.my/public/exchange-rate/JPY?session=0900*' => Http::response($this->bnmRate('2026-11-16', 2.5562)),
        ]);

        $this->actingAs(User::factory()->create())->getJson('/api/exchange-rates/jpy-myr')
            ->assertOk()
            ->assertJsonPath('data.rm_per_100_yen', 2.5562)
            ->assertJsonPath('data.session', '0900')
            ->assertJsonPath('data.source', 'Bank Negara Malaysia');

        $this->actingAs(User::factory()->create())->getJson('/api/exchange-rates/jpy-myr')->assertOk();
        Http::assertSentCount(3);
    }

    public function test_it_looks_back_to_the_last_business_day(): void
    {
        Http::preventStrayRequests();
        $this->travelTo('2026-11-15 10:00:00'); // a Sunday
        Http::fake([
            'api.bnm.gov.my/public/exchange-rate/JPY/date/2026-11-13?session=1700*' => Http::response($this->bnmRate('2026-11-13', 2.5558)),
            'api.bnm.gov.my/*' => Http::response(['message' => 'No records found.', 'code' => 404], 404),
        ]);

        $this->actingAs(User::factory()->create())->getJson('/api/exchange-rates/jpy-myr')
            ->assertOk()
            ->assertJsonPath('data.date', '2026-11-13')
            ->assertJsonPath('data.rm_per_100_yen', 2.5558);
    }

    public function test_it_falls_back_to_the_ecb_when_bank_negara_is_down(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.bnm.gov.my/*' => Http::response('Server error', 500),
            'api.frankfurter.dev/*' => Http::response(['amount' => 1, 'base' => 'JPY', 'date' => '2026-11-13', 'rates' => ['MYR' => 0.025547]]),
        ]);

        $this->actingAs(User::factory()->create())->getJson('/api/exchange-rates/jpy-myr')
            ->assertOk()
            ->assertJsonPath('data.rm_per_100_yen', 2.5547)
            ->assertJsonPath('data.source', 'European Central Bank');
    }

    public function test_it_reports_when_no_rate_is_available(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('down', 500)]);

        $this->actingAs(User::factory()->create())->getJson('/api/exchange-rates/jpy-myr')
            ->assertStatus(503);
    }

    public function test_guests_cannot_call_it(): void
    {
        $this->getJson('/api/exchange-rates/jpy-myr')->assertUnauthorized();
    }
}

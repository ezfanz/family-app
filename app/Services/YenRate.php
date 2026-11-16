<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Japanese yen to Malaysian ringgit, as RM per ¥100.
 *
 * Source: Bank Negara Malaysia's official exchange rate API (middle rate of
 * the latest session, looking back over weekends and holidays). If BNM is
 * unreachable, falls back to the European Central Bank reference rate via
 * Frankfurter. Results are cached so the APIs are called a few times a day.
 */
class YenRate
{
    private const CACHE_KEY = 'fx:jpy-myr';

    /**
     * BNM publishes several sessions a day; later sessions are fresher.
     *
     * @var list<string>
     */
    private const SESSIONS = ['1700', '1200', '0900'];

    /**
     * @return array{rm_per_100_yen: float, buying: float|null, selling: float|null, date: string, session: string|null, source: string, source_url: string, fetched_at: string}
     */
    public function current(): array
    {
        $minutes = (int) config('services.exchange_rates.cache_minutes', 180);

        return Cache::remember(self::CACHE_KEY, now()->addMinutes($minutes), fn () => $this->fetch());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{rm_per_100_yen: float, buying: float|null, selling: float|null, date: string, session: string|null, source: string, source_url: string, fetched_at: string}
     */
    private function fetch(): array
    {
        return $this->fromBankNegara() ?? $this->fromEcb()
            ?? throw new RuntimeException('The exchange rate services are not reachable right now.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromBankNegara(): ?array
    {
        $base = rtrim((string) config('services.exchange_rates.bnm_url'), '/');
        $today = CarbonImmutable::now('Asia/Kuala_Lumpur');

        for ($daysBack = 0; $daysBack <= 7; $daysBack++) {
            $day = $today->subDays($daysBack);
            foreach (self::SESSIONS as $session) {
                $url = $daysBack === 0
                    ? "{$base}/exchange-rate/JPY"
                    : "{$base}/exchange-rate/JPY/date/{$day->toDateString()}";

                $rate = $this->bnmRequest($url, $session);
                if ($rate !== null) {
                    return $rate;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function bnmRequest(string $url, string $session): ?array
    {
        try {
            $response = Http::timeout(6)
                ->withHeaders(['Accept' => 'application/vnd.BNM.API.v1+json'])
                ->get($url, ['session' => $session, 'quote' => 'rm']);
        } catch (ConnectionException) {
            return null;
        }

        $rate = $response->json('data.rate');
        $middle = $rate['middle_rate'] ?? null;
        if (! $response->successful() || ! is_numeric($middle) || (int) $response->json('data.unit') !== 100) {
            return null;
        }

        return [
            'rm_per_100_yen' => round((float) $middle, 4),
            'buying' => is_numeric($rate['buying_rate'] ?? null) ? round((float) $rate['buying_rate'], 4) : null,
            'selling' => is_numeric($rate['selling_rate'] ?? null) ? round((float) $rate['selling_rate'], 4) : null,
            'date' => (string) ($rate['date'] ?? now()->toDateString()),
            'session' => $session,
            'source' => 'Bank Negara Malaysia',
            'source_url' => 'https://www.bnm.gov.my/exchange-rates',
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromEcb(): ?array
    {
        $base = rtrim((string) config('services.exchange_rates.frankfurter_url'), '/');

        try {
            $response = Http::timeout(6)->get("{$base}/latest", ['base' => 'JPY', 'symbols' => 'MYR']);
        } catch (ConnectionException) {
            return null;
        }

        $perYen = $response->json('rates.MYR');
        if (! $response->successful() || ! is_numeric($perYen)) {
            return null;
        }

        return [
            'rm_per_100_yen' => round((float) $perYen * 100, 4),
            'buying' => null,
            'selling' => null,
            'date' => (string) $response->json('date', now()->toDateString()),
            'session' => null,
            'source' => 'European Central Bank',
            'source_url' => 'https://www.ecb.europa.eu/stats/policy_and_exchange_rates/euro_reference_exchange_rates/',
            'fetched_at' => now()->toIso8601String(),
        ];
    }
}

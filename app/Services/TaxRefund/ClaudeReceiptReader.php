<?php

namespace App\Services\TaxRefund;

use Anthropic\Client;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

/**
 * Reads a Japanese receipt with Claude vision. The quick model reads first;
 * the careful model reads again only when the quick one is unsure about the
 * store, the date or the amount. Per field, the more confident answer wins.
 */
class ClaudeReceiptReader implements ReceiptReader
{
    /** Fields that decide eligibility or the refund; unsure ones get a second look. */
    private const KEY_FIELDS = ['store_name', 'date', 'amount_yen', 'tax_rate'];

    private const FIELDS = ['store_name', 'branch', 'date', 'time', 'amount_yen', 'tax_rate'];

    private const INSTRUCTIONS = <<<'TEXT'
        You read photos of shop receipts from Japan for a traveller's tax-refund tracker.
        The photos are parts of one receipt, top part first.

        Return:
        - store_name: the shop or chain as a traveller would say it, in English or romaji (e.g. "Don Quijote", "Uniqlo", "Matsumoto Kiyoshi", "Bic Camera"). Empty if you can't tell.
        - branch: the branch or area (e.g. "Dotonbori", "Shinjuku East Exit"), without words like "store" or 店. Empty if none is printed.
        - date: purchase date as YYYY-MM-DD. Convert Japanese era years (令和8年 = 2026). Empty if unreadable.
        - time: purchase time as HH:MM, 24-hour. Empty if not printed.
        - amount_yen and amount_kind: on a tax-free (免税) receipt, prefer the before-tax total of the tax-free goods (免税対象額, 税抜合計, 免税合計) with amount_kind "pre_tax". If only the total including tax is printed (合計, 税込), give that with amount_kind "total". Use 0 and "none" if you can't find a total. Whole yen only.
        - tax_rate: 10, or 8 for food and drink (軽減税率 / ※ marks). If both rates appear, compare the 8%対象 and 10%対象 amounts and give the rate with the larger amount, with confidence at most 0.6 so the traveller checks it.
        - items: every purchased line, top to bottom (at most 40). name_en is a short plain-English name a Malaysian traveller would recognise: translate, and add what it is when the brand alone says little (e.g. "KitKat Matcha", "Tokyo Banana sponge cakes, 8 pcs", "Shiseido face cream"). name_ja is the line as printed. qty is 1 unless a quantity is printed. price_yen is the line amount as printed. reduced_rate is true for 8% items (※ or 軽 marks). Leave out subtotals, tax, discounts summary, points and payment lines.
        - summary: one short English sentence for the traveller about what this receipt is, e.g. "Tax-free shopping at Don Quijote Dotonbori: snacks, a face cream and a plush toy." Empty if it isn't a receipt.
        - is_receipt: false if the photo isn't a shop receipt.
        - confidence: for each field, from 0 (guess) to 1 (clearly printed). Use below 0.6 for anything blurred, cut off, folded or inferred.

        Never return names, passport numbers or card numbers, even if they are printed, and never put them in the items or summary.
        TEXT;

    public function __construct(
        private readonly Client $client,
        private readonly string $quickModel,
        private readonly string $carefulModel,
        private readonly float $minConfidence = 0.75,
    ) {}

    /** Whether automatic reading is switched on and has an API key. */
    public static function isConfigured(): bool
    {
        return config('tax_refund.reader') === 'claude' && filled(config('services.anthropic.key'));
    }

    /**
     * @param  list<array{data: string, mime: string}>  $images
     * @return array{fields: array<string, mixed>, confidence: array<string, float>, model: string|null}
     */
    public function read(array $images): array
    {
        $quick = self::rateFromItems($this->ask($this->quickModel, $images));
        if (! $this->needsSecondLook($quick)) {
            return $quick;
        }

        return self::rateFromItems(self::merge($quick, $this->ask($this->carefulModel, $images)));
    }

    /**
     * Work the tax rate out from the items rather than trust a guess: the rate
     * covering more yen wins. A receipt with both rates stays flagged (0.6),
     * since one rate can only approximate it; a single-rate one is clear (0.9).
     *
     * @param  array{fields: array<string, mixed>, confidence: array<string, float>, items?: list<array<string, mixed>>, model: string|null}  $result
     * @return array{fields: array<string, mixed>, confidence: array<string, float>, items?: list<array<string, mixed>>, model: string|null}
     */
    public static function rateFromItems(array $result): array
    {
        $reduced = 0;
        $standard = 0;
        foreach ($result['items'] ?? [] as $item) {
            if ($item['reduced_rate'] ?? false) {
                $reduced += (int) ($item['price_yen'] ?? 0);
            } else {
                $standard += (int) ($item['price_yen'] ?? 0);
            }
        }
        if ($reduced + $standard === 0) {
            return $result;
        }

        $result['fields']['tax_rate'] = $reduced > $standard ? 8 : 10;
        $result['confidence']['tax_rate'] = $reduced > 0 && $standard > 0 ? 0.6 : max(0.9, $result['confidence']['tax_rate'] ?? 0.0);
        $result['rate_from_items'] = true;

        return $result;
    }

    /**
     * True when the store, date or amount is missing or below the confidence bar.
     *
     * @param  array{fields: array<string, mixed>, confidence: array<string, float>, model: string|null}  $result
     */
    public function needsSecondLook(array $result): bool
    {
        foreach (self::KEY_FIELDS as $field) {
            // A rate worked out from the items can't be improved by reading again.
            if ($field === 'tax_rate' && ($result['rate_from_items'] ?? false)) {
                continue;
            }
            if (! array_key_exists($field, $result['fields']) || ($result['confidence'][$field] ?? 0.0) < $this->minConfidence) {
                return true;
            }
        }

        return false;
    }

    /**
     * Field by field, keep the more confident reading.
     *
     * @param  array{fields: array<string, mixed>, confidence: array<string, float>, model: string|null}  $first
     * @param  array{fields: array<string, mixed>, confidence: array<string, float>, model: string|null}  $second
     * @return array{fields: array<string, mixed>, confidence: array<string, float>, model: string|null}
     */
    public static function merge(array $first, array $second): array
    {
        $result = ['fields' => [], 'confidence' => [], 'model' => $second['model'] ?? $first['model']];
        foreach (self::FIELDS as $field) {
            $pick = ($second['confidence'][$field] ?? -1.0) >= ($first['confidence'][$field] ?? -1.0) ? $second : $first;
            if (array_key_exists($field, $pick['fields'])) {
                $result['fields'][$field] = $pick['fields'][$field];
                $result['confidence'][$field] = $pick['confidence'][$field] ?? 0.0;
            }
        }
        if (isset($result['fields']['amount_yen'])) {
            $source = ($second['fields']['amount_yen'] ?? null) === $result['fields']['amount_yen'] ? $second : $first;
            $result['fields']['amount_kind'] = $source['fields']['amount_kind'] ?? 'total';
        }
        // The careful model's item list and summary, unless it found none.
        $result['items'] = ($second['items'] ?? []) !== [] ? $second['items'] : ($first['items'] ?? []);
        $result['summary'] = ($second['summary'] ?? '') !== '' ? $second['summary'] : ($first['summary'] ?? '');

        return $result;
    }

    /**
     * Turn the model's raw answer into clean fields, dropping anything empty or implausible.
     *
     * @param  array<string, mixed>  $raw
     * @return array{fields: array<string, mixed>, confidence: array<string, float>, model: string|null}
     */
    public static function normalize(array $raw, ?string $model = null): array
    {
        $empty = ['fields' => [], 'confidence' => [], 'items' => [], 'summary' => '', 'model' => $model];
        if (($raw['is_receipt'] ?? true) === false) {
            return $empty;
        }

        $fields = [];
        $store = trim((string) ($raw['store_name'] ?? ''));
        if ($store !== '') {
            $fields['store_name'] = mb_substr($store, 0, 80);
        }
        $branch = trim((string) ($raw['branch'] ?? ''));
        if ($branch !== '') {
            $fields['branch'] = mb_substr($branch, 0, 80);
        }
        $date = (string) ($raw['date'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && self::isPlausibleDate($date)) {
            $fields['date'] = $date;
        }
        $time = (string) ($raw['time'] ?? '');
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1) {
            $fields['time'] = $time;
        }
        $amount = (int) ($raw['amount_yen'] ?? 0);
        $kind = $raw['amount_kind'] ?? 'none';
        if ($amount > 0 && $amount <= 10_000_000 && in_array($kind, ['pre_tax', 'total'], true)) {
            $fields['amount_yen'] = $amount;
            $fields['amount_kind'] = $kind;
        }
        $rate = (int) ($raw['tax_rate'] ?? 0);
        if (in_array($rate, config('tax_refund.rates', [10, 8]), true)) {
            $fields['tax_rate'] = $rate;
        }

        $confidence = [];
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $fields)) {
                $confidence[$field] = round(min(1.0, max(0.0, (float) Arr::get($raw, "confidence.{$field}", 0.0))), 2);
            }
        }

        return [
            'fields' => $fields,
            'confidence' => $confidence,
            'items' => self::cleanItems($raw['items'] ?? []),
            'summary' => mb_substr(trim((string) ($raw['summary'] ?? '')), 0, 200),
            'model' => $model,
        ];
    }

    /**
     * @param  mixed  $items
     * @return list<array{name_en: string, name_ja: string, qty: int, price_yen: int, reduced_rate: bool}>
     */
    private static function cleanItems($items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn ($item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'name_en' => mb_substr(trim((string) ($item['name_en'] ?? '')), 0, 80),
                'name_ja' => mb_substr(trim((string) ($item['name_ja'] ?? '')), 0, 80),
                'qty' => max(1, min(999, (int) ($item['qty'] ?? 1))),
                'price_yen' => max(0, min(10_000_000, (int) ($item['price_yen'] ?? 0))),
                'reduced_rate' => (bool) ($item['reduced_rate'] ?? false),
            ])
            ->filter(fn (array $item): bool => $item['name_en'] !== '' || $item['name_ja'] !== '')
            ->take(40)
            ->values()
            ->all();
    }

    /** A receipt from the last two years and not in the future (Japan time). */
    private static function isPlausibleDate(string $date): bool
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, config('tax_refund.timezone'));
        $today = CarbonImmutable::now(config('tax_refund.timezone'))->startOfDay();

        return $parsed !== null && $parsed->format('Y-m-d') === $date && $parsed->lte($today->addDay()) && $parsed->gte($today->subYears(2));
    }

    /**
     * @param  list<array{data: string, mime: string}>  $images
     * @return array{fields: array<string, mixed>, confidence: array<string, float>, model: string|null}
     */
    private function ask(string $model, array $images): array
    {
        $content = [];
        foreach ($images as $image) {
            $content[] = [
                'type' => 'image',
                'source' => ['type' => 'base64', 'mediaType' => $image['mime'], 'data' => base64_encode($image['data'])],
            ];
        }
        $content[] = ['type' => 'text', 'text' => 'Read this receipt.'];

        $message = $this->client->messages->create(
            model: $model,
            maxTokens: 4096,
            system: self::INSTRUCTIONS,
            messages: [['role' => 'user', 'content' => $content]],
            outputConfig: ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => self::schema()]],
        );

        if ($message->stopReason === 'refusal') {
            return ['fields' => [], 'confidence' => [], 'model' => $model];
        }
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $raw = json_decode($block->text, true);

                return self::normalize(is_array($raw) ? $raw : [], $model);
            }
        }

        return ['fields' => [], 'confidence' => [], 'model' => $model];
    }

    /**
     * @return array<string, mixed>
     */
    private static function schema(): array
    {
        $number = ['type' => 'number'];

        return [
            'type' => 'object',
            'properties' => [
                'is_receipt' => ['type' => 'boolean'],
                'store_name' => ['type' => 'string'],
                'branch' => ['type' => 'string'],
                'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, or empty'],
                'time' => ['type' => 'string', 'description' => 'HH:MM 24-hour, or empty'],
                'amount_yen' => ['type' => 'integer'],
                'amount_kind' => ['type' => 'string', 'enum' => ['pre_tax', 'total', 'none']],
                'tax_rate' => ['type' => 'integer', 'enum' => [10, 8]],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name_en' => ['type' => 'string'],
                            'name_ja' => ['type' => 'string'],
                            'qty' => ['type' => 'integer'],
                            'price_yen' => ['type' => 'integer'],
                            'reduced_rate' => ['type' => 'boolean'],
                        ],
                        'required' => ['name_en', 'name_ja', 'qty', 'price_yen', 'reduced_rate'],
                        'additionalProperties' => false,
                    ],
                ],
                'summary' => ['type' => 'string'],
                'confidence' => [
                    'type' => 'object',
                    'properties' => array_fill_keys(self::FIELDS, $number),
                    'required' => self::FIELDS,
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['is_receipt', 'store_name', 'branch', 'date', 'time', 'amount_yen', 'amount_kind', 'tax_rate', 'items', 'summary', 'confidence'],
            'additionalProperties' => false,
        ];
    }
}

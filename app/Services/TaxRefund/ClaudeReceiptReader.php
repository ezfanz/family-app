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
    /** Fields that decide eligibility; unsure ones get a second look. */
    private const KEY_FIELDS = ['store_name', 'date', 'amount_yen'];

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
        - tax_rate: 10, or 8 for food and drink (軽減税率 / ※ marks). If both rates appear, give the rate covering the larger amount and lower its confidence.
        - is_receipt: false if the photo isn't a shop receipt.
        - confidence: for each field, from 0 (guess) to 1 (clearly printed). Use below 0.6 for anything blurred, cut off, folded or inferred.

        Never return names, passport numbers or card numbers, even if they are printed.
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
        $quick = $this->ask($this->quickModel, $images);
        if (! $this->needsSecondLook($quick)) {
            return $quick;
        }

        return self::merge($quick, $this->ask($this->carefulModel, $images));
    }

    /**
     * True when the store, date or amount is missing or below the confidence bar.
     *
     * @param  array{fields: array<string, mixed>, confidence: array<string, float>, model: string|null}  $result
     */
    public function needsSecondLook(array $result): bool
    {
        foreach (self::KEY_FIELDS as $field) {
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
        $empty = ['fields' => [], 'confidence' => [], 'model' => $model];
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

        return ['fields' => $fields, 'confidence' => $confidence, 'model' => $model];
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
            maxTokens: 2048,
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
                'confidence' => [
                    'type' => 'object',
                    'properties' => array_fill_keys(self::FIELDS, $number),
                    'required' => self::FIELDS,
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['is_receipt', 'store_name', 'branch', 'date', 'time', 'amount_yen', 'amount_kind', 'tax_rate', 'confidence'],
            'additionalProperties' => false,
        ];
    }
}

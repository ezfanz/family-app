<?php

namespace App\Imports;

use App\Enums\ShoppingCategory;
use App\Enums\TripRole;
use App\Models\ShoppingItem;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns the "Shopping list" sheet of the E-Itinerary Excel template into
 * shopping items. analyse() validates every row and plans create/update
 * without saving; apply() saves a plan.
 *
 * Re-importing the same file updates rows instead of duplicating them: a row
 * matches an existing item on the same person's list by item name + details.
 * Empty cells never overwrite values already in the app (e.g. a price typed
 * on the phone), and items missing from the file are left untouched.
 */
class ShoppingListImport
{
    public const SHEET = 'Shopping list';

    public const MAX_ROWS = 500;

    /**
     * Template header text (lowercased) => import key.
     *
     * @var array<string, string>
     */
    private const HEADERS = [
        'no' => 'no',
        'store' => 'store',
        'item name' => 'item_name',
        'item' => 'item_name',
        'details (colour, size, code)' => 'details',
        'details' => 'details',
        'qty' => 'qty',
        'quantity' => 'qty',
        'est. price each (¥)' => 'est_price_yen',
        'est. price each' => 'est_price_yen',
        'price each (¥)' => 'est_price_yen',
        'for (who)' => 'for_who',
        'for who' => 'for_who',
        'category' => 'category',
        'buyer' => 'buyer',
        'link' => 'link',
        'notes' => 'notes',
        'bought (y/n)' => 'bought',
        'bought' => 'bought',
        'paid (¥)' => 'paid_yen',
        'paid' => 'paid_yen',
    ];

    /**
     * @var array<string, int> lowercased name or first name => user id
     */
    private array $memberIds = [];

    /**
     * @var array<int, string>
     */
    private array $memberNames = [];

    public function __construct(private Trip $trip, private User $importer)
    {
        foreach ($trip->members()->get() as $member) {
            $this->memberNames[$member->id] = $member->name;
            $full = Str::lower(trim($member->name));
            $this->memberIds[$full] ??= $member->id;
            $this->memberIds[Str::before($full, ' ')] ??= $member->id;
        }
    }

    /**
     * Read and validate a file without saving anything.
     *
     * @return array{rows: list<array<string, mixed>>, summary: array{create: int, update: int, unchanged: int, error: int}}
     */
    public function analyse(string $path): array
    {
        $sheetRows = (new XlsxSheetReader($path))->rows(self::SHEET, self::MAX_ROWS + 20);
        [$headerRow, $columns] = $this->findHeader($sheetRows);

        $existing = $this->existingItemsByKey();
        $seen = [];
        $plan = [];

        foreach ($sheetRows as $number => $cells) {
            if ($number <= $headerRow) {
                continue;
            }
            $values = [];
            foreach ($columns as $column => $key) {
                $values[$key] = trim($cells[$column] ?? '');
            }
            $hasContent = collect($values)->except(['no'])->filter(fn ($v) => $v !== '')->isNotEmpty();
            if (! $hasContent || $this->isSummaryRow($values)) {
                continue;
            }
            if (count($plan) >= self::MAX_ROWS) {
                throw new RuntimeException('Up to '.self::MAX_ROWS.' items per file. Split the list into smaller files.');
            }

            $plan[] = $this->planRow($number, $values, $existing, $seen);
        }

        if ($plan === []) {
            throw new RuntimeException('No items found. Fill in at least one row with an Item name.');
        }

        return ['rows' => $plan, 'summary' => $this->summarise($plan)];
    }

    /**
     * Save the valid rows of a plan from analyse().
     *
     * @param  list<array<string, mixed>>  $plan
     * @return array{create: int, update: int, unchanged: int, error: int}
     */
    public function apply(array $plan): array
    {
        DB::transaction(function () use ($plan): void {
            $sortOrder = (int) $this->trip->shoppingItems()->max('sort_order');
            foreach ($plan as $row) {
                if ($row['action'] === 'create') {
                    $item = new ShoppingItem($row['attributes']);
                    $item->trip_id = $this->trip->id;
                    $item->user_id = $row['user_id'];
                    $item->sort_order = ++$sortOrder;
                    $item->save();
                } elseif ($row['action'] === 'update') {
                    ShoppingItem::whereKey($row['item_id'])->first()?->fill($row['attributes'])->save();
                }
            }
        });

        return $this->summarise($plan);
    }

    /**
     * Strip internal fields for the API response.
     *
     * @param  list<array<string, mixed>>  $plan
     * @return list<array<string, mixed>>
     */
    public static function present(array $plan): array
    {
        return array_map(fn (array $row) => [
            'row' => $row['row'],
            'name' => $row['name'],
            'details' => $row['details'] ?? null,
            'buyer' => $row['buyer'],
            'action' => $row['action'],
            'message' => $row['message'],
        ], $plan);
    }

    /**
     * The template ends with a "Total ... Paid" row of sums. Rows without an
     * item name whose text is only such labels (and numbers) are not items.
     *
     * @param  array<string, string>  $values
     */
    private function isSummaryRow(array $values): bool
    {
        if (($values['item_name'] ?? '') !== '') {
            return false;
        }

        $labels = collect($values)->except(['no'])
            ->filter(fn ($v) => $v !== '' && ! is_numeric(str_replace([',', '¥'], '', $v)))
            ->map(fn ($v) => Str::lower(trim($v, ' :')));

        return $labels->isNotEmpty()
            && $labels->every(fn ($label) => in_array($label, ['total', 'totals', 'grand total', 'subtotal', 'sub total', 'jumlah', 'paid', 'estimated', 'spent'], true));
    }

    /**
     * @param  array<int, array<string, string>>  $sheetRows
     * @return array{0: int, 1: array<string, string>}
     */
    private function findHeader(array $sheetRows): array
    {
        foreach (array_slice($sheetRows, 0, 10, true) as $number => $cells) {
            $columns = [];
            foreach ($cells as $column => $text) {
                $label = Str::of($text)->lower()->squish()->toString();
                if (isset(self::HEADERS[$label])) {
                    $columns[$column] = self::HEADERS[$label];
                }
            }
            if (in_array('item_name', $columns, true)) {
                return [$number, $columns];
            }
        }

        throw new RuntimeException('Could not find the header row with "Item name". Use the E-Itinerary template and keep the header row as it is.');
    }

    /**
     * @param  array<string, string>  $values
     * @param  array<string, ShoppingItem>  $existing
     * @param  array<string, int>  $seen
     * @return array<string, mixed>
     */
    private function planRow(int $number, array $values, array $existing, array &$seen): array
    {
        $name = $values['item_name'] ?? '';
        $row = ['row' => $number, 'name' => $name, 'buyer' => null, 'user_id' => null, 'action' => 'error', 'message' => null, 'attributes' => [], 'item_id' => null];

        try {
            if ($name === '') {
                throw new RowError('Item name is empty.');
            }
            $this->limit($name, 160, 'Item name');

            $row['details'] = $values['details'] ?? null;

            $userId = $this->buyerId($values['buyer'] ?? '');
            $row['user_id'] = $userId;
            $row['buyer'] = $this->memberNames[$userId] ?? null;

            $qty = $this->wholeNumber($values['qty'] ?? '', 'Qty', 1, 999);
            $price = $this->yen($values['est_price_yen'] ?? '', 'Est. price each');
            $paid = $this->yen($values['paid_yen'] ?? '', 'Paid');
            $bought = $this->yesNo($values['bought'] ?? '');

            $attributes = array_filter([
                'name' => $name,
                'store' => $this->limit($values['store'] ?? '', 60, 'Store'),
                'details' => $this->limit($values['details'] ?? '', 160, 'Details'),
                'for_whom' => $this->limit($values['for_who'] ?? '', 80, 'For (who)'),
                'link' => $this->link($values['link'] ?? ''),
                'notes' => $this->limit($values['notes'] ?? '', 1000, 'Notes'),
            ], fn ($v) => $v !== null && $v !== '');

            if (trim((string) ($values['category'] ?? '')) !== '') {
                $attributes['category'] = ShoppingCategory::fromText($values['category'])->value;
            }
            if ($qty !== null) {
                $attributes['qty'] = $qty;
            }
            if ($price !== null) {
                $total = (int) round(($qty ?? 1) * $price);
                if ($total > 10_000_000) {
                    throw new RowError('Estimated total is too large.');
                }
                $attributes['estimated_yen'] = $total;
            }
            if ($paid !== null) {
                $attributes['actual_yen'] = $paid;
            }
            if ($bought !== null) {
                $attributes['is_bought'] = $bought;
            }
            if (($paid ?? 0) > 0) {
                $attributes['is_bought'] = true;
            }

            $key = $userId.'|'.Str::lower($name).'|'.Str::lower($attributes['details'] ?? '');
            if (isset($seen[$key])) {
                throw new RowError("Same item as row {$seen[$key]}. Give it different details or remove one.");
            }
            $seen[$key] = $number;

            $match = $existing[$key] ?? null;
            if ($match === null) {
                $row['action'] = 'create';
                $row['attributes'] = $attributes + ['qty' => 1, 'estimated_yen' => 0, 'is_bought' => false];
            } else {
                $changes = array_filter($attributes, fn ($value, $field) => $match->getAttribute($field) != $value, ARRAY_FILTER_USE_BOTH);
                $row['item_id'] = $match->id;
                $row['action'] = $changes === [] ? 'unchanged' : 'update';
                $row['attributes'] = $changes;
            }
        } catch (RowError $error) {
            $row['action'] = 'error';
            $row['message'] = $error->getMessage();
        }

        return $row;
    }

    private function buyerId(string $buyer): int
    {
        if ($buyer === '') {
            return $this->importer->id;
        }

        $key = Str::lower(Str::squish($buyer));
        $userId = $this->memberIds[$key] ?? $this->memberIds[Str::before($key, ' ')] ?? null;
        if ($userId === null) {
            throw new RowError("Buyer “{$buyer}” is not in this trip.");
        }
        if ($userId !== $this->importer->id && $this->trip->roleFor($this->importer) !== TripRole::Owner) {
            throw new RowError("Only the trip owner can add to {$this->memberNames[$userId]}’s list.");
        }

        return $userId;
    }

    private function limit(string $value, int $max, string $label): ?string
    {
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $max) {
            throw new RowError("{$label} is longer than {$max} characters.");
        }

        return $value;
    }

    private function wholeNumber(string $value, string $label, int $min, int $max): ?int
    {
        if ($value === '') {
            return null;
        }
        $number = $this->number($value, $label);
        if (floor($number) != $number || $number < $min || $number > $max) {
            throw new RowError("{$label} must be a whole number from {$min} to {$max}.");
        }

        return (int) $number;
    }

    private function yen(string $value, string $label): ?int
    {
        if ($value === '') {
            return null;
        }
        $number = $this->number($value, $label);
        if ($number < 0 || $number > 10_000_000) {
            throw new RowError("{$label} must be between ¥0 and ¥10,000,000.");
        }

        return (int) round($number);
    }

    private function number(string $value, string $label): float
    {
        $clean = str_replace(['¥', '￥', ',', ' ', 'JPY', 'jpy'], '', $value);
        if (! is_numeric($clean)) {
            throw new RowError("{$label} “{$value}” is not a number.");
        }

        return (float) $clean;
    }

    private function yesNo(string $value): ?bool
    {
        if ($value === '') {
            return null;
        }

        return match (Str::lower($value)) {
            'y', 'yes', 'true', '1', 'ya' => true,
            'n', 'no', 'false', '0', 'tidak' => false,
            default => throw new RowError("Bought must be Y or N, not “{$value}”."),
        };
    }

    private function link(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > 500 || ! filter_var($value, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $value)) {
            throw new RowError('Link must be a web address starting with http:// or https://.');
        }

        return $value;
    }

    /**
     * @return array<string, ShoppingItem>
     */
    private function existingItemsByKey(): array
    {
        $byKey = [];
        foreach ($this->trip->shoppingItems()->whereNotNull('user_id')->get() as $item) {
            $byKey[$item->user_id.'|'.Str::lower($item->name).'|'.Str::lower($item->details ?? '')] ??= $item;
        }

        return $byKey;
    }

    /**
     * @param  list<array<string, mixed>>  $plan
     * @return array{create: int, update: int, unchanged: int, error: int}
     */
    private function summarise(array $plan): array
    {
        $summary = ['create' => 0, 'update' => 0, 'unchanged' => 0, 'error' => 0];
        foreach ($plan as $row) {
            $summary[$row['action']]++;
        }

        return $summary;
    }
}

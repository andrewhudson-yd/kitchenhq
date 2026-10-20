<?php

namespace App\Services;

use App\Models\Household;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseReconciliation
{
    public static function name(string $raw): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $raw));
        $name = preg_replace('/^(?:JS|Sainsburys|Sainsbury\x{2019}s|Sainsbury\x{0027}s|Tesco|ASDA|ALDI|LIDL|CO[- ]?OP)\s+/iu', '', $name);
        $name = preg_replace('/\b\d+(?:\.\d+)?\s*(?:kg|g|ml|litres?|l)\b/iu', '', $name);
        $name = preg_replace('/\b(?:x\s*\d+|\d+\s*pack|pack of \d+)\b/iu', '', $name);
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        return mb_strlen($name) ? Str::ucfirst(mb_strtolower($name)) : $raw;
    }

    public function shopping(Household $h, string $week)
    {
        return collect(app(DailyShopping::class)->forWeek($h, CarbonImmutable::parse($week))['groups'])->flatten(1)->keyBy('item_key');
    }

    public function prepare(Household $h, string $week, array $names, ?int $draftId = null): int
    {
        return DB::transaction(function () use ($h, $week, $names, $draftId) {
            Household::whereKey($h->id)->lockForUpdate()->firstOrFail();
            if ($draftId && $existing = DB::table('purchase_batches')->where('household_id', $h->id)->where('shopping_photo_draft_id', $draftId)->first()) {
                return $existing->id;
            }
            $shopping = $this->shopping($h, $week);
            $inventory = $h->inventory()->with('location')->get();
            $aliases = DB::table('receipt_item_aliases')->where('household_id', $h->id)->pluck('inventory_item_id', 'alias_key');
            $rows = [];
            $add = function (string $name, string $source) use (&$rows, $inventory, $shopping, $aliases) {
                $nice = self::name($name);
                $key = SimpleStock::key($nice);
                $stockMatches = $inventory->filter(fn ($i) => SimpleStock::key($i->name) === $key);
                $stock = $stockMatches->count() === 1 ? $stockMatches->first() : null;
                if (isset($aliases[$key])) {
                    $stock = $inventory->firstWhere('id', $aliases[$key]);
                }
                $list = $shopping->get(hash('sha256', $key)) ?? ($stock ? $shopping->get(hash('sha256', SimpleStock::key($stock->name))) : null);
                $identity = $stock ? 'stock:'.$stock->id : $key;
                if (isset($rows[$identity])) {
                    if ($rows[$identity]['source'] !== $source) {
                        $rows[$identity]['source'] = 'Receipt + picked up';
                    }
                    return;
                }
                $rows[$identity] = ['name' => $stock?->name ?? $list['name'] ?? $nice, 'receipt_name' => $nice, 'inventory_id' => $stock?->id, 'location' => $stock?->location->type ?? $this->location($nice), 'shopping_key' => $list['item_key'] ?? '', 'source' => $source, 'ambiguous' => ! $stock && $stockMatches->count() > 1];
            };
            foreach ($names as $name) {
                $add($name, 'Receipt');
            }
            $checks = DB::table('shopping_checks')->where('household_id', $h->id)->where('week', $week)->where('checked', true)->whereNull('stocked_at')->where('removed', false)->pluck('item_key');
            foreach ($checks as $key) {
                if ($item = $shopping->get($key)) {
                    // Kits are separate records; do not create food stock for them.
                    if (! str_starts_with($item['name'], 'SimplyCook kit: ')) {
                        $add($item['name'], 'Picked up');
                    }
                }
            }
            if (! count($rows)) {
                throw ValidationException::withMessages(['items' => 'No purchases to save yet. Tick Got it or read a receipt first.']);
            }
            return DB::table('purchase_batches')->insertGetId(['household_id' => $h->id, 'shopping_photo_draft_id' => $draftId, 'week' => $week, 'rows' => json_encode(array_values($rows), JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    private function location(string $name): string
    {
        $key = mb_strtolower($name);
        if (preg_match('/frozen|ice cream/', $key)) {
            return 'freezer';
        }
        if (preg_match('/milk|yogh?urt|cheese|butter|chicken|beef|pork|ham|turkey|salmon|prawns|lettuce|broccoli|cabbage|carrots|cucumber|peppers|berries/', $key) && ! preg_match('/coconut|powder|sauce|stock|tinned/', $key)) {
            return 'fridge';
        }
        return 'pantry';
    }

    public function save(Household $h, int $batchId, array $submitted): int
    {
        return DB::transaction(function () use ($h, $batchId, $submitted) {
            Household::whereKey($h->id)->lockForUpdate()->firstOrFail();
            $batch = DB::table('purchase_batches')->where('household_id', $h->id)->where('id', $batchId)->lockForUpdate()->firstOrFail();
            if ($batch->applied_at) {
                return 0;
            }
            $snapshot = json_decode($batch->rows, true, 512, JSON_THROW_ON_ERROR);
            $shopping = $this->shopping($h, $batch->week);
            $results = [];
            foreach ($submitted as $index => $row) {
                if (empty($row['include'])) {
                    continue;
                }
                if (! array_key_exists($index, $snapshot)) {
                    throw ValidationException::withMessages(['rows' => 'This review has changed. Please open it again.']);
                }
                if (str_contains(mb_strtolower($row['name']), '[unclear]')) {
                    throw ValidationException::withMessages(['rows' => 'Correct or skip unclear names before saving.']);
                }
                $listKey = $row['shopping_key'] ?? '';
                if ($listKey && ! $shopping->has($listKey)) {
                    throw ValidationException::withMessages(['rows' => 'A shopping-list match has changed. Reopen the review.']);
                }
                $location = $h->locations()->firstOrCreate(['name' => ['pantry' => 'Cupboard', 'fridge' => 'Fridge', 'freezer' => 'Freezer'][$row['location']]], ['type' => $row['location']]);
                $item = ! empty($row['inventory_id']) ? $h->inventory()->whereKey($row['inventory_id'])->firstOrFail() : null;
                if (! $item) {
                    $matches = $h->inventory()->get()->filter(fn ($i) => SimpleStock::key($i->name) === SimpleStock::key($row['name']));
                    if ($matches->count() > 1) {
                        throw ValidationException::withMessages(['rows' => 'Choose which existing stock entry to update for '.$row['name'].'.']);
                    }
                    $item = $matches->first();
                }
                $before = $item ? ['id' => $item->id, 'level' => $item->qualitative_stock, 'quantity' => $item->quantity, 'location_id' => $item->storage_location_id, 'expires_at' => $item->expires_at?->toDateString()] : null;
                if (! $item) {
                    $item = $h->inventory()->create(['name' => self::name($row['name']), 'storage_location_id' => $location->id, 'unit' => 'each']);
                }
                // Qualitative replenishment: never add made-up grams or reuse an old expiry date.
                $item->update(['qualitative_stock' => 'plenty', 'quantity' => null, 'expires_at' => null, 'storage_location_id' => $location->id]);
                foreach (array_unique([$snapshot[$index]['receipt_name'] ?? $snapshot[$index]['name'], $row['name']]) as $alias) {
                    DB::table('receipt_item_aliases')->updateOrInsert(['household_id' => $h->id, 'alias_key' => SimpleStock::key($alias)], ['inventory_item_id' => $item->id]);
                }
                $keys = array_unique(array_filter([$listKey, hash('sha256', SimpleStock::key($item->name)), hash('sha256', SimpleStock::key($row['name']))]));
                foreach ($keys as $key) {
                    DB::table('shopping_checks')->updateOrInsert(['household_id' => $h->id, 'week' => $batch->week, 'item_key' => $key], ['checked' => true, 'removed' => false, 'stocked_at' => now()]);
                }
                $results[] = ['inventory_id' => $item->id, 'name' => $item->name, 'before' => $before, 'source_name' => $snapshot[$index]['name'], 'shopping_keys' => $keys];
            }
            if (! count($results)) {
                throw ValidationException::withMessages(['rows' => 'Select at least one purchase to save.']);
            }
            DB::table('purchase_batches')->where('id', $batch->id)->update(['result' => json_encode($results, JSON_THROW_ON_ERROR), 'applied_at' => now(), 'updated_at' => now()]);
            if ($batch->shopping_photo_draft_id) {
                DB::table('shopping_photo_drafts')->where('id', $batch->shopping_photo_draft_id)->update(['imported_at' => now(), 'updated_at' => now()]);
            }
            return count($results);
        });
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Services\DailyShopping;
use App\Services\ShoppingActions;
use App\Services\SimpleStock;
use App\Support\FoodDictionary;
use App\Support\ShoppingQuantity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The phone app's offline shopping list.
 *
 * The app keeps its own copy of the list and records what you do while offline as a queue of
 * operations. When there's signal it posts them here; each one goes through ShoppingActions,
 * exactly like the web page, and the fresh list comes back. Operations are idempotent (tick =
 * "set ticked"), so a retry after a dropped connection is harmless.
 */
class AppShoppingController extends Controller
{
    private const OPS = ['got', 'quantity', 'remove', 'add'];

    /** Create a device token while signed in on the website (called by the app, once per install). */
    public function token(Request $r)
    {
        $data = $r->validate(['device' => 'nullable|string|max:100']);
        $token = Str::random(48);
        DB::table('app_tokens')->insert(['user_id' => $r->user()->id, 'name' => $data['device'] ?? null, 'token_hash' => hash('sha256', $token), 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['token' => $token] + $this->who($r));
    }

    public function show(Request $r, DailyShopping $shopping)
    {
        return response()->json($this->payload($this->household($r), $shopping));
    }

    public function sync(Request $r, DailyShopping $shopping, ShoppingActions $actions)
    {
        $r->validate(['ops' => 'present|array|max:500', 'ops.*.id' => 'required|string|max:64', 'ops.*.type' => 'required|string|in:'.implode(',', self::OPS)]);
        $h = $this->household($r);
        $results = [];
        foreach ($r->input('ops') as $op) {
            try {
                $this->apply($h, $op, $actions);
                $results[] = ['id' => $op['id'], 'ok' => true];
            } catch (ValidationException $e) {
                // A malformed operation is dropped so it can't block everything queued behind it.
                $results[] = ['id' => $op['id'], 'ok' => false, 'error' => collect($e->errors())->flatten()->first()];
            }
        }

        return response()->json($this->payload($h, $shopping) + ['results' => $results]);
    }

    private function apply(Household $h, array $op, ShoppingActions $actions): void
    {
        $v = validator($op, [
            'week' => 'nullable|date_format:Y-m-d',
            'name' => 'nullable|string|max:150',
            'key' => 'nullable|regex:/^[a-f0-9]{64}$/',
            'value' => 'nullable',
            'quantity' => 'nullable|string|max:30',
        ])->validate();
        $week = $v['week'] ?? $h->currentWeek()->toDateString();
        $key = $v['key'] ?? (isset($v['name']) ? ShoppingActions::key($v['name']) : null);

        match ($op['type']) {
            'add' => $actions->add($h, $this->required($v, 'name')),
            'got' => $actions->got($h, $week, $this->required($v, 'name'), filter_var($op['value'] ?? true, FILTER_VALIDATE_BOOLEAN)),
            'quantity' => $actions->quantity($h, $week, $this->requiredKey($key), $v['quantity'] ?? null),
            'remove' => $actions->remove($h, $week, $this->requiredKey($key), filter_var($op['value'] ?? true, FILTER_VALIDATE_BOOLEAN)),
        };
    }

    private function required(array $v, string $field): string
    {
        if (! isset($v[$field]) || trim($v[$field]) === '') {
            throw ValidationException::withMessages([$field => "The {$field} is required."]);
        }

        return $v[$field];
    }

    private function requiredKey(?string $key): string
    {
        return $key ?? throw ValidationException::withMessages(['key' => 'An item key or name is required.']);
    }

    /** The week's list as the app shows it: items still to get, plus those ticked off this week. */
    private function payload(Household $h, DailyShopping $shopping): array
    {
        $week = $h->currentWeek();
        ['groups' => $groups] = $shopping->forWeek($h, $week);
        $places = $h->inventory()->with('location')->get()->mapWithKeys(fn ($i) => [SimpleStock::key($i->name) => $i->location->type])->all();
        $picked = $h->inventory()->whereNotNull('shelf')->get()->mapWithKeys(fn ($i) => [SimpleStock::key($i->name) => FoodDictionary::sectionForShelf($i->shelf)])->all();

        $items = [];
        foreach ($groups as $rows) {
            foreach ($rows as $row) {
                $done = $row['reason'] === 'Bought and saved to Stock';
                if (! $done && $row['action'] !== 'buy') {
                    continue; // "check at home" / "already have" aren't on the in-shop list
                }
                $key = SimpleStock::key($row['name']);
                $items[] = [
                    'key' => $row['item_key'],
                    'name' => $row['name'],
                    'note' => $this->note($row),
                    'section' => $picked[$key] ?? FoodDictionary::section($row['name'], $places[$key] ?? null),
                    'quantity' => $row['quantity'],
                    'label' => ShoppingQuantity::label($row['quantity']),
                    'done' => $done,
                ];
            }
        }

        return [
            'week' => $week->toDateString(),
            'household' => $h->name,
            'sections' => FoodDictionary::SECTIONS,
            'items' => $items,
            'synced_at' => now()->toIso8601String(),
        ];
    }

    /** Same wording as the shopping page. */
    private function note(array $row): ?string
    {
        return match (true) {
            $row['reason'] === 'Out' => 'Ran out',
            $row['reason'] === 'Nearly out' => 'Running low',
            $row['reason'] === 'Added by you', str_starts_with($row['reason'], 'Added from'), $row['reason'] === 'Bought and saved to Stock' => null,
            str_starts_with($row['uses'][0] ?? '', 'Check ') => $row['uses'][0],
            default => count($row['uses']) && $row['uses'][0] !== 'Stock replenishment' ? 'For '.collect($row['uses'])->take(2)->implode(', ') : null,
        };
    }

    private function household(Request $r): Household
    {
        return $r->user()->household()->firstOrFail();
    }

    private function who(Request $r): array
    {
        return ['user' => ['id' => $r->user()->id, 'name' => $r->user()->name], 'household' => $this->household($r)->name];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Services\MealIngredientExchange;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShoppingExchangeController extends Controller
{
    public function form(Request $request)
    {
        $request->validate(['week' => 'nullable|date_format:Y-m-d']);

        return view('shopping-exchange', ['week' => $request->input('week', $request->user()->household->plan_starts_on ?? today()->startOfWeek()->toDateString())]);
    }

    public function example(string $format)
    {
        abort_unless(in_array($format, ['csv', 'json'], true), 404);

        return response()->download(resource_path('import-examples/meal-ingredients.'.$format), 'meal-ingredients.'.$format, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function prepare(Request $request, MealIngredientExchange $exchange)
    {
        $data = $request->validate([
            'week' => 'required|date_format:Y-m-d',
            'format' => ['required', Rule::in(['auto', 'csv', 'json'])],
            'exchange_file' => 'nullable|file|max:512',
            'exchange_text' => 'nullable|string|max:524288',
        ]);
        if ($request->hasFile('exchange_file') === $request->filled('exchange_text')) {
            throw ValidationException::withMessages(['exchange' => 'Choose a file or paste text, using one input at a time.']);
        }
        $payload = $exchange->parse($request->hasFile('exchange_file') ? $request->file('exchange_file')->get() : $data['exchange_text'], $data['format']);
        $token = (string) Str::uuid();
        $draft = [
            'household_id' => $request->user()->household_id,
            'week' => $data['week'], 'payload' => $payload,
            'fingerprint' => $exchange->fingerprint($payload),
            'source_name' => $request->hasFile('exchange_file') ? mb_substr(basename($request->file('exchange_file')->getClientOriginalName()), 0, 180) : 'Pasted meal ingredients',
            'expires_at' => now()->addMinutes(30)->timestamp,
        ];
        $drafts = collect($request->session()->get('meal_exchange_previews', []))->filter(fn ($d) => $d['expires_at'] > now()->timestamp)->take(-2)->all();
        $drafts[$token] = $draft;
        $request->session()->put('meal_exchange_previews', $drafts);

        return redirect()->route('shopping.exchange.preview', $token);
    }

    private function draft(Request $request, string $token): array
    {
        $draft = $request->session()->get('meal_exchange_previews', [])[$token] ?? null;
        abort_unless($draft && (int) $draft['household_id'] === (int) $request->user()->household_id && $draft['expires_at'] > now()->timestamp, 404, 'This preview expired. Upload or paste the file again.');

        return $draft;
    }

    public function preview(Request $request, string $token)
    {
        $draft = $this->draft($request, $token);
        $existing = DB::table('meal_ingredient_imports')->where('household_id', $draft['household_id'])->where('fingerprint', $draft['fingerprint'])->first();

        $overlaps = $this->overlaps($draft);

        return view('shopping-exchange-preview', compact('draft', 'token', 'existing', 'overlaps'));
    }

    private function overlaps(array $draft): array
    {
        $identity = fn ($recipe) => json_encode([$recipe['meal_date'], $recipe['meal_type'], mb_strtolower($recipe['recipe_name'])]);
        $proposed = collect($draft['payload']['recipes'])->keyBy($identity);
        $names = [];
        foreach (DB::table('meal_ingredient_imports')->where('household_id', $draft['household_id'])->get(['payload']) as $saved) {
            foreach (json_decode($saved->payload, true, 32, JSON_THROW_ON_ERROR)['recipes'] as $recipe) {
                if ($proposed->has($identity($recipe))) {
                    $names[] = $recipe['recipe_name'];
                }
            }
        }

        return array_values(array_unique($names));
    }

    public function save(Request $request, string $token)
    {
        $draft = $this->draft($request, $token);
        $request->validate(['reviewed' => 'accepted']);
        [$week, $duplicate] = DB::transaction(function () use ($draft, $request) {
            Household::whereKey($draft['household_id'])->lockForUpdate()->firstOrFail();
            $existing = DB::table('meal_ingredient_imports')->where('household_id', $draft['household_id'])->where('fingerprint', $draft['fingerprint'])->first();
            if ($existing) {
                return [$existing->week, true];
            }
            if ($this->overlaps($draft)) {
                $request->validate(['allow_overlap' => 'accepted'], ['allow_overlap.accepted' => 'This meal already has imported ingredients. Review the overlap and explicitly choose to add a separate copy.']);
            }
            DB::table('meal_ingredient_imports')->insert([
                'household_id' => $draft['household_id'], 'week' => $draft['week'],
                'fingerprint' => $draft['fingerprint'], 'source_name' => $draft['source_name'],
                'payload' => json_encode($draft['payload'], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return [$draft['week'], false];
        });

        return redirect()->route('shopping', ['week' => CarbonImmutable::parse($week)->toDateString()])->with('status', $duplicate ? 'This meal ingredient file is already imported. No duplicates were added.' : 'Meal ingredients added below your shopping list. Stock and purchases were not changed.');
    }

    public function remove(Request $request, int $id)
    {
        $householdId = $request->user()->household_id;
        $week = DB::transaction(function () use ($householdId, $id) {
            Household::whereKey($householdId)->lockForUpdate()->firstOrFail();
            $import = DB::table('meal_ingredient_imports')->where('household_id', $householdId)->where('id', $id)->firstOrFail();
            DB::table('meal_ingredient_imports')->where('id', $import->id)->delete();

            return $import->week;
        });

        return redirect()->route('shopping', ['week' => $week])->with('status', 'Imported ingredient file removed. Stock and purchases were not changed.');
    }
}

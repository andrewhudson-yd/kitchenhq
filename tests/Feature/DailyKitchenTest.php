<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Services\DailyShopping;
use App\Services\PersonalHouseholdImport;
use App\Services\TodaySetup;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DailyKitchenTest extends TestCase
{
    use RefreshDatabase;

    private function kitchen()
    {
        $user = app(PersonalHouseholdImport::class)->run('daily@example.test', Hash::make('only-a-test-password'));
        app(TodaySetup::class)->apply($user->household);
        return $user->fresh();
    }

    public function test_confirmed_week_locations_staples_and_ticks_are_saved(): void
    {
        $user = $this->kitchen();
        $h = $user->household;
        $this->assertSame('2026-10-20', $h->plan_starts_on);
        $this->assertSame(24, $h->meals()->count());
        $this->assertSame(2, $h->meals()->whereHas('recipe', fn ($q) => $q->where('is_simplycook', true))->count());
        $this->assertSame(7, $h->meals()->where('audience', 'Noah')->where('slot', 'early_dinner')->count());
        $this->assertTrue($h->inventory()->with('location')->get()->every(fn ($i) => in_array($i->location->name, ['Freezer', 'Fridge', 'Cupboard'])));
        app(TodaySetup::class)->apply($h);
        $this->assertSame(24, $h->meals()->count());
        $list = app(DailyShopping::class)->forWeek($h, CarbonImmutable::parse('2026-10-20'));
        $buy = collect($list['groups']['buy'])->pluck('name')->map(fn ($n) => strtolower($n));
        $this->assertTrue($buy->contains('peas'));
        $this->assertTrue($buy->contains('cheese'));
        $this->assertFalse($buy->contains('chips'));
        $this->assertTrue(collect($list['groups']['check'])->pluck('name')->contains('Turkey slices'));
        $this->actingAs($user);
        foreach (['/inventory', '/planner', '/shopping', '/assistant'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/planner?week=2026-10-20')->assertSee('Lamb keema')->assertSee('26 Oct');
        $this->get('/inventory?place=freezer')->assertSee('Running low')->assertSee("We're out", false)->assertDontSee('Low stock threshold');
        $this->get('/inventory?place=pantry')->assertSee('Crunchy peanut butter');
        $this->get('/inventory?place=attic')->assertSessionHasErrors('place');
        $this->get('/')->assertRedirect('/inventory');
        $key = $list['groups']['buy'][0]['item_key'];
        $this->post('/shopping/check', ['week' => '2026-10-20', 'item_key' => $key, 'checked' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('shopping_checks', ['household_id' => $h->id, 'item_key' => $key, 'checked' => 1]);
    }

    public function test_qualitative_stock_updates_invalidate_old_counts_and_are_scoped(): void
    {
        $u = $this->kitchen();
        $h = $u->household;
        $item = $h->inventory()->first();
        $item->update(['quantity' => 100]);
        $this->actingAs($u)->post('/stock/'.$item->id.'/level', ['level' => 'low'])->assertSessionHasNoErrors();
        $this->assertNull($item->fresh()->quantity);
        $this->assertSame('low', $item->fresh()->qualitative_stock);
        $this->post('/stock/'.$item->id.'/level', ['level' => 'out'])->assertSessionHasNoErrors();
        $this->assertEquals(0, $item->fresh()->quantity);
        $other = Household::create(['name' => 'Other', 'join_code' => hash('sha256', 'other')]);
        $location = $other->locations()->create(['name' => 'Private fridge', 'type' => 'fridge']);
        $foreign = $other->inventory()->create(['name' => 'Private food', 'storage_location_id' => $location->id, 'unit' => 'each']);
        $this->post('/stock/'.$foreign->id.'/level', ['level' => 'out'])->assertNotFound();
        $this->post('/stock/'.$item->id.'/level', ['level' => 'low', 'location_id' => $location->id])->assertSessionHasErrors('location_id');
    }

    public function test_editing_meal_shopping_outline_changes_the_list(): void
    {
        $u = $this->kitchen();
        $recipe = $u->household->recipes()->where('name', 'Lamb keema & naan')->firstOrFail();
        $this->actingAs($u)->put('/recipes/'.$recipe->id, ['name' => $recipe->name, 'servings' => 2, 'planning_only' => 1, 'shopping_ingredients' => "lamb mince\nnaan\nnew ingredient"])->assertSessionHasNoErrors();
        $this->assertSame(['lamb mince', 'naan', 'new ingredient'], $recipe->fresh()->shopping_ingredients);
        $list = app(DailyShopping::class)->forWeek($u->household, CarbonImmutable::parse('2026-10-20'));
        $this->assertTrue(collect($list['groups']['check'])->pluck('name')->contains('new ingredient'));
    }


    public function test_kitchen_out_goes_to_the_list_and_got_it_puts_it_back(): void
    {
        $u = $this->kitchen();
        $h = $u->household;
        $week = $h->currentWeek()->toDateString();
        $this->actingAs($u)->post('/stock/add', ['name' => 'Oat milk', 'place' => 'fridge'])->assertRedirect('/inventory?place=fridge');
        $milk = $h->inventory()->where('name', 'Oat milk')->firstOrFail();
        $this->assertSame('plenty', $milk->qualitative_stock);
        $this->assertSame('fridge', $milk->location->type);
        // Adding the same food again updates it instead of making a duplicate.
        $this->post('/stock/add', ['name' => 'oat milk', 'place' => 'fridge']);
        $this->assertSame(1, $h->inventory()->whereRaw('lower(name) = ?', ['oat milk'])->count());

        $this->post('/stock/'.$milk->id.'/level', ['level' => 'out'])->assertSessionHas('status', 'Oat milk added to the shopping list.');
        $this->get('/inventory?place=fridge')->assertDontSee('id="stock-'.$milk->id.'"', false);
        $this->get('/shopping')->assertSee('Oat milk')->assertSee('Ran out')->assertSee('Dairy &amp; eggs', false);
        $buy = collect(app(DailyShopping::class)->forWeek($h, CarbonImmutable::parse($week))['groups']['buy'])->pluck('name');
        $this->assertTrue($buy->contains('Oat milk'));

        $this->post('/shopping/got', ['week' => $week, 'name' => 'Oat milk', 'got' => 1])->assertSessionHasNoErrors()->assertSessionHas('shopping_got.name', 'Oat milk');
        $this->get('/shopping')->assertDontSee('id="got-'.hash('sha256', 'oat milk').'"', false)->assertDontSee('For this week')->assertDontSee('back in the kitchen</small>', false);
        $this->assertSame('plenty', $milk->fresh()->qualitative_stock);
        $this->get('/inventory?place=fridge')->assertSee('id="stock-'.$milk->id.'"', false);
        $list = app(DailyShopping::class)->forWeek($h, CarbonImmutable::parse($week));
        $this->assertFalse(collect($list['groups']['buy'])->pluck('name')->contains('Oat milk'));

        $this->post('/shopping/got', ['week' => $week, 'name' => 'Oat milk', 'got' => 0]);
        $this->assertSame('out', $milk->fresh()->qualitative_stock);

        // Things added straight to the list land in their usual place once bought.
        $this->post('/shopping/add', ['food' => 'Fish fingers'])->assertRedirect('/shopping');
        $this->post('/stock/add', ['food' => 'Raspberries', 'place' => 'fridge'])->assertSessionHasNoErrors();
        $this->assertSame(1, $h->inventory()->where('name', 'Raspberries')->count());
        $this->get('/shopping')->assertSee('Fish fingers');
        $this->post('/shopping/got', ['week' => $week, 'name' => 'Fish fingers', 'got' => 1]);
        $this->assertSame('freezer', $h->inventory()->where('name', 'Fish fingers')->firstOrFail()->location->type);

        $this->post('/shopping/got', ['week' => $week, 'name' => 'Oat milk', 'got' => 1]);
        $this->delete('/stock/'.$milk->id)->assertSessionHasNoErrors()->assertSessionHas('kitchen_removed', ['name' => 'Oat milk', 'place' => 'fridge', 'level' => 'plenty']);
        $this->assertNull($milk->fresh());
        // Undo puts it back where it was.
        $this->post('/stock/add', ['name' => 'Oat milk', 'place' => 'fridge', 'level' => 'plenty']);
        $this->assertSame('fridge', $h->inventory()->where('name', 'Oat milk')->firstOrFail()->location->type);
    }

    public function test_food_dictionary_suggests_foods_with_their_usual_place(): void
    {
        $this->assertSame('freezer', \App\Support\FoodDictionary::placeFor('fish fingers'));
        $this->assertSame('fridge', \App\Support\FoodDictionary::placeFor('Milk'));
        $this->assertSame('pantry', \App\Support\FoodDictionary::placeFor('something unusual'));
        foreach (['Frozen king prawns' => 'Frozen', 'Chicken stock cubes' => 'Cupboard', 'Chicken' => 'Meat & fish', 'Submarine rolls' => 'Bakery', 'Strawberries' => 'Fruit & veg', 'Crisps' => 'Snacks & drinks', 'Coconut milk' => 'Cupboard', 'Milk' => 'Dairy & eggs', 'Butter / spread' => 'Dairy & eggs', 'Frozen ready meals / dumplings' => 'Frozen', 'Fresh soup' => 'Chilled', 'Toothpaste' => 'Toiletries', 'Sun cream' => 'Toiletries', 'Toilet roll' => 'Cleaning & household', 'Washing-up liquid' => 'Cleaning & household', 'Spray oil' => 'Cupboard'] as $food => $section) {
            $this->assertSame($section, \App\Support\FoodDictionary::section($food), $food);
        }
        $this->assertSame('Chilled', \App\Support\FoodDictionary::section('Mystery dip', 'fridge'));
        $this->assertSame('Toiletries', \App\Support\FoodDictionary::label('toothpaste'));
        $this->assertNull(\App\Support\FoodDictionary::label('Milk'));
        $this->assertGreaterThan(200, count(\App\Support\FoodDictionary::all()));
        $this->actingAs($this->kitchen())->get('/inventory')->assertSee('food-suggestions', false)->assertSee('Fish fingers');
    }

    public function test_shopping_list_quantities(): void
    {
        $u = $this->kitchen();
        $h = $u->household;
        $week = $h->currentWeek()->toDateString();
        $key = hash('sha256', \App\Services\SimpleStock::key('Semi-skimmed milk'));
        $this->actingAs($u)->post('/shopping/add', ['food' => '2 semi-skimmed milk'])->assertSessionHas('status', '2 Semi-skimmed milk added to the list.');
        $this->assertDatabaseHas('shopping_checks', ['household_id' => $h->id, 'item_key' => $key, 'quantity' => '2']);
        $this->assertDatabaseHas('shopping_extras', ['household_id' => $h->id, 'item_key' => $key, 'name' => 'semi-skimmed milk']);
        $this->get('/shopping')->assertSee('×2');

        $this->post('/shopping/quantity', ['week' => $week, 'item_key' => $key, 'quantity' => '2', 'step' => 1]);
        $this->assertDatabaseHas('shopping_checks', ['item_key' => $key, 'quantity' => '3']);
        $this->post('/shopping/quantity', ['week' => $week, 'item_key' => $key, 'quantity' => '4 pints']);
        $this->assertDatabaseHas('shopping_checks', ['item_key' => $key, 'quantity' => '4 pints']);
        $this->post('/shopping/quantity', ['week' => $week, 'item_key' => $key, 'quantity' => '2', 'step' => -1]);
        $this->assertDatabaseHas('shopping_checks', ['item_key' => $key, 'quantity' => null]);
        $this->post('/shopping/quantity', ['week' => $week, 'item_key' => 'nope', 'quantity' => '2'])->assertSessionHasErrors('item_key');

        // A quantity typed in the kitchen is ignored rather than becoming part of the name.
        $this->post('/stock/add', ['food' => '3 tins chopped tomatoes', 'place' => 'pantry']);
        $this->assertSame(0, $h->inventory()->where('name', 'like', '3 tins%')->count());
        $this->assertSame(1, $h->inventory()->whereRaw('lower(name) = ?', ['chopped tomatoes'])->count());

        foreach (['milk x2' => ['milk', '2'], '500 g mince' => ['mince', '500g'], 'Milk 2 pints' => ['Milk', '2 pints'], '7up' => ['7up', null], 'bread' => ['bread', null]] as $text => $parsed) {
            $this->assertSame($parsed, \App\Support\ShoppingQuantity::parse($text), $text);
        }
        foreach ([['2 tins', -1, '1 tin'], ['1 loaf', 1, '2 loaves'], ['500g', 1, '600g'], ['2', -1, null], [null, 1, '2']] as [$from, $by, $to]) {
            $this->assertSame($to, \App\Support\ShoppingQuantity::step($from, $by));
        }
    }
}

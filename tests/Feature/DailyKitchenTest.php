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
        $this->get('/planner')->assertSee('Lamb keema')->assertSee('26 Oct');
        $this->get('/inventory')->assertSee('Nearly out')->assertDontSee('Low stock threshold');
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

}

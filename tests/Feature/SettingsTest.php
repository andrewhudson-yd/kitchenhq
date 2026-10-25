<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\DailyShopping;
use App\Services\PersonalHouseholdImport;
use App\Services\SimpleStock;
use App\Services\TodaySetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function kitchen(): User
    {
        $user = app(PersonalHouseholdImport::class)->run('settings@example.test', Hash::make('only-a-test-password'));
        app(TodaySetup::class)->apply($user->household);

        return $user->fresh();
    }

    private function toBuy(Household $h)
    {
        $groups = app(DailyShopping::class)->forWeek($h, $h->currentWeek())['groups'];

        return collect($groups['buy'])->merge($groups['check']);
    }

    public function test_clearing_the_shopping_list_keeps_the_kitchen(): void
    {
        $u = $this->kitchen();
        $h = $u->household;
        $this->actingAs($u)->post('/shopping/add', ['food' => '2 bread']);
        $kept = $h->inventory()->get()->reject(fn ($i) => SimpleStock::level($i) === 'out')->count();
        $this->assertGreaterThan(0, $this->toBuy($h)->count());
        $this->get('/settings')->assertOk()->assertSee('Clear the shopping list')->assertSee('Empty the freezer');

        $this->post('/settings/clear-shopping')->assertRedirect('/settings')->assertSessionHas('status', 'Shopping list cleared.');
        $this->assertCount(0, $this->toBuy($h));
        $this->assertDatabaseMissing('shopping_extras', ['household_id' => $h->id]);
        $this->assertSame($kept, $h->inventory()->count());

        // Running out of something again puts it back on the list.
        $item = $h->inventory()->first();
        $this->post('/stock/'.$item->id.'/level', ['level' => 'out']);
        $this->assertTrue($this->toBuy($h)->pluck('name')->contains($item->name));
    }

    public function test_emptying_one_place_or_the_whole_kitchen(): void
    {
        $u = $this->kitchen();
        $h = $u->household;
        $freezer = $h->inventory()->whereHas('location', fn ($q) => $q->where('type', 'freezer'))->firstOrFail();
        StockMovement::create(['household_id' => $h->id, 'inventory_item_id' => $freezer->id, 'change' => -1, 'reason' => 'Cooked']);
        $fridge = $h->inventory()->whereHas('location', fn ($q) => $q->where('type', 'fridge'))->count();

        $this->actingAs($u)->post('/settings/clear-kitchen', ['place' => 'freezer'])->assertSessionHasNoErrors();
        $this->assertSame(0, $h->inventory()->whereHas('location', fn ($q) => $q->where('type', 'freezer'))->get()->reject(fn ($i) => SimpleStock::level($i) === 'out')->count());
        $this->assertSame($fridge, $h->inventory()->whereHas('location', fn ($q) => $q->where('type', 'fridge'))->count());
        $this->assertNull($freezer->fresh());

        $this->post('/settings/clear-kitchen', ['place' => 'all']);
        $this->assertSame(0, $h->inventory()->get()->reject(fn ($i) => SimpleStock::level($i) === 'out')->count());
        $this->post('/settings/clear-kitchen', ['place' => 'garage'])->assertSessionHasErrors('place');
    }

    public function test_clearing_only_touches_your_own_household(): void
    {
        $u = $this->kitchen();
        $other = Household::create(['name' => 'Other', 'join_code' => hash('sha256', 'other')]);
        $location = $other->locations()->create(['name' => 'Fridge', 'type' => 'fridge']);
        $theirs = $other->inventory()->create(['name' => 'Their milk', 'storage_location_id' => $location->id, 'unit' => 'each', 'qualitative_stock' => 'plenty']);
        $gone = $other->inventory()->create(['name' => 'Their peas', 'storage_location_id' => $location->id, 'unit' => 'each', 'qualitative_stock' => 'out']);

        $this->actingAs($u)->post('/settings/clear-kitchen', ['place' => 'all']);
        $this->post('/settings/clear-shopping');
        $this->assertNotNull($theirs->fresh());
        $this->assertNotNull($gone->fresh());
    }
}

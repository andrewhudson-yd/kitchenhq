<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use App\Services\DailyShopping;
use App\Services\SimpleStock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_removal_is_persistent_reversible_and_scoped_to_household_and_week(): void
    {
        $h = Household::create(['name' => 'Test', 'join_code' => bin2hex(random_bytes(32))]);
        $u = User::factory()->create(['household_id' => $h->id]);
        $location = $h->locations()->create(['name' => 'Freezer', 'type' => 'freezer']);
        $item = $h->inventory()->create(['name' => 'peas', 'storage_location_id' => $location->id, 'unit' => 'each', 'qualitative_stock' => 'out']);
        $key = hash('sha256', SimpleStock::key('peas'));
        $payload = ['week' => '2026-10-20', 'item_key' => $key, 'removed' => 1];
        $this->actingAs($u)->post('/shopping/remove', $payload)->assertRedirect('/shopping?week=2026-10-20')->assertSessionHas('shopping_removed');
        $service = app(DailyShopping::class);
        $this->assertCount(0, $service->forWeek($h, CarbonImmutable::parse('2026-10-20'))['groups']['buy']);
        $this->get('/shopping?week=2026-10-20')->assertDontSee('Got peas');
        $this->assertCount(1, $service->forWeek($h, CarbonImmutable::parse('2026-10-27'))['groups']['buy']);
        $this->assertSame('out', $item->fresh()->qualitative_stock);
        $other = Household::create(['name' => 'Other', 'join_code' => bin2hex(random_bytes(32))]);
        $this->actingAs(User::factory()->create(['household_id' => $other->id]))->post('/shopping/remove', array_merge($payload, ['removed' => 0]));
        $this->assertDatabaseHas('shopping_checks', ['household_id' => $h->id, 'item_key' => $key, 'removed' => true]);
        $this->actingAs($u)->post('/shopping/remove', array_merge($payload, ['removed' => 0]))->assertSessionHasNoErrors();
        $this->assertCount(1, $service->forWeek($h, CarbonImmutable::parse('2026-10-20'))['groups']['buy']);
    }
}

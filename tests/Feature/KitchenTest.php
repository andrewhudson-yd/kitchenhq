<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use App\Services\CookMeal;
use App\Services\HouseholdSetup;
use App\Services\ShoppingList;
use App\Services\WeekPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KitchenTest extends TestCase
{
    use RefreshDatabase;

    private function household(string $name = 'Test kitchen'): Household
    {
        return Household::create(['name' => $name, 'join_code' => hash('sha256', Str::random(32))]);
    }

    private function user(Household $h): User
    {
        return User::factory()->create(['household_id' => $h->id]);
    }

    private function stock(Household $h, string $name, ?float $quantity, string $unit = 'g', ?string $expiry = null)
    {
        $location = $h->locations()->firstOrCreate(['name' => 'Test freezer'], ['type' => 'freezer']);

        return $h->inventory()->create(['storage_location_id' => $location->id, 'name' => $name, 'quantity' => $quantity, 'unit' => $unit, 'minimum_quantity' => 0, 'expires_at' => $expiry]);
    }

    private function meal(Household $h, array $ingredients, int $servings = 1, array $recipeData = [])
    {
        $recipe = $h->recipes()->create(array_merge(['name' => 'Test recipe', 'servings' => 1], $recipeData));
        $recipe->ingredients()->createMany($ingredients);

        return $h->meals()->create(['recipe_id' => $recipe->id, 'date' => '2026-10-20', 'slot' => 'late_dinner', 'audience' => 'Adults', 'servings' => $servings]);
    }

    public function test_registration_seeds_preferences_but_no_invented_stock(): void
    {
        $this->post('/register', ['name' => 'Alex', 'email' => 'alex@example.test', 'password' => 'GoodPassword123!', 'password_confirmation' => 'GoodPassword123!'])->assertRedirect('/');
        $this->assertAuthenticated();
        $h = User::first()->household;
        $this->assertSame(3, $h->profiles()->count());
        $this->assertSame(0, $h->inventory()->count());
        $this->assertSame(12, $h->recipes()->count());
        $this->assertStringContainsString('chicken mince', mb_strtolower($h->profiles()->where('name', 'You')->first()->dislikes));
    }

    public function test_all_main_pages_render_for_a_household(): void
    {
        $h = $this->household();
        app(HouseholdSetup::class)->seed($h);
        app(WeekPlanner::class)->generate($h, CarbonImmutable::parse('2026-10-19'));
        $this->actingAs($this->user($h));
        foreach (['/', '/inventory', '/planner?week=2026-10-19', '/recipes', '/recipes?edit='.$h->recipes()->first()->id, '/shopping?week=2026-10-19', '/family'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_households_cannot_read_or_mutate_each_others_records(): void
    {
        $a = $this->household('A');
        $b = $this->household('B');
        $secret = $this->stock($b, 'secret steak', 500);
        $meal = $this->meal($b, [['name' => 'secret steak', 'quantity' => 100, 'unit' => 'g']]);
        $profile = $b->profiles()->create(['name' => 'Private name']);
        $this->actingAs($this->user($a));
        $this->get('/inventory')->assertOk()->assertDontSee('secret steak');
        $this->put('/inventory/'.$secret->id, ['name' => 'secret steak', 'quantity' => 0, 'minimum_quantity' => 0, 'unit' => 'g', 'storage_location_id' => $secret->storage_location_id])->assertSessionHasErrors('storage_location_id');
        $this->post('/meals/'.$meal->id.'/cook')->assertNotFound();
        $this->delete('/meals/'.$meal->id)->assertNotFound();
        $this->put('/profiles/'.$profile->id, ['name' => 'Changed'])->assertNotFound();
        $this->get('/recipes?edit='.$meal->recipe_id)->assertNotFound();
        $this->post('/meals', ['recipe_id' => $meal->recipe_id, 'date' => '2026-10-20', 'slot' => 'lunch', 'audience' => 'You', 'servings' => 1])->assertSessionHasErrors('recipe_id');
    }

    public function test_week_template_has_separate_dinners_roast_two_kits_and_lunches_and_is_idempotent(): void
    {
        $h = $this->household();
        app(HouseholdSetup::class)->seed($h);
        $planner = app(WeekPlanner::class);
        $week = CarbonImmutable::parse('2026-10-19');
        $planner->generate($h, $week);
        $planner->generate($h, $week);
        $this->assertSame(24, $h->meals()->count());
        $this->assertSame(7, $h->meals()->where('slot', 'early_dinner')->where('audience', 'Noah')->count());
        $this->assertSame(5, $h->meals()->where('slot', 'packed_lunch')->count());
        $this->assertSame(5, $h->meals()->where('slot', 'lunch')->count());
        $this->assertSame([], $planner->warnings($h, $week));
        $this->assertTrue($h->meals()->with('recipe')->whereDate('date', '2026-10-25')->where('audience', 'Adults')->first()->recipe->is_roast);
        $this->assertSame(2, $h->meals()->whereHas('recipe', fn ($q) => $q->where('is_simplycook', true))->count());
    }

    public function test_shopping_aggregates_scaled_meals_and_subtracts_only_verified_matching_in_date_stock(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-20'));
        $this->travelTo(now()->setDate(2026, 10, 20));
        $h = $this->household();
        $this->meal($h, [['name' => 'peas', 'quantity' => 100, 'unit' => 'g']], 2);
        $this->stock($h, 'peas', 50);
        $this->stock($h, 'peas', null);
        $this->stock($h, 'peas', 99, 'pack');
        $this->stock($h, 'peas', 1000, 'g', '2026-10-19');
        $list = app(ShoppingList::class)->forWeek($h, CarbonImmutable::parse('2026-10-19'));
        $this->assertEquals(200, $list['rows'][0]['required']);
        $this->assertEquals(50, $list['rows'][0]['stock']);
        $this->assertEquals(150, $list['rows'][0]['buy']);
        $this->assertTrue($list['rows'][0]['unknown']);
        CarbonImmutable::setTestNow();
    }

    public function test_cooking_uses_earliest_expiry_and_is_idempotent(): void
    {
        $h = $this->household();
        $meal = $this->meal($h, [['name' => 'peas', 'quantity' => 100, 'unit' => 'g']]);
        $later = $this->stock($h, 'peas', 100, 'g', today()->addDays(5)->toDateString());
        $early = $this->stock($h, 'peas', 60, 'g', today()->addDay()->toDateString());
        $service = app(CookMeal::class);
        $service->handle($h, $meal->id);
        $service->handle($h, $meal->id);
        $this->assertEquals(0, $early->fresh()->quantity);
        $this->assertEquals(60, $later->fresh()->quantity);
        $this->assertNotNull($meal->fresh()->cooked_at);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_insufficient_stock_rolls_back_all_deductions(): void
    {
        $h = $this->household();
        $meal = $this->meal($h, [['name' => 'peas', 'quantity' => 100, 'unit' => 'g'], ['name' => 'beef', 'quantity' => 200, 'unit' => 'g']]);
        $peas = $this->stock($h, 'peas', 100);
        $this->stock($h, 'beef', null);
        try {
            app(CookMeal::class)->handle($h, $meal->id);
            $this->fail('Should reject unknown stock');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('beef', $e->getMessage());
        }
        $this->assertEquals(100, $peas->fresh()->quantity);
        $this->assertNull($meal->fresh()->cooked_at);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_repeated_ingredients_cannot_overdraw_stock(): void
    {
        $h = $this->household();
        $meal = $this->meal($h, [['name' => 'peas', 'quantity' => 70, 'unit' => 'g'], ['name' => 'peas', 'quantity' => 70, 'unit' => 'g']]);
        $stock = $this->stock($h, 'peas', 100);
        try {
            app(CookMeal::class)->handle($h, $meal->id);
            $this->fail('Should reject');
        } catch (ValidationException) {
        }
        $this->assertEquals(100, $stock->fresh()->quantity);
    }

    public function test_kit_shortage_rolls_back_ingredients_then_sufficient_kits_are_deducted(): void
    {
        $h = $this->household();
        $kit = $h->kits()->create(['name' => 'mild beef', 'brand' => 'simplycook', 'quantity' => null]);
        $meal = $this->meal($h, [['name' => 'beef', 'quantity' => 100, 'unit' => 'g']], 3, ['servings' => 2, 'is_simplycook' => true, 'recipe_kit_id' => $kit->id]);
        $beef = $this->stock($h, 'beef', 300);
        $service = app(CookMeal::class);
        try {
            $service->handle($h, $meal->id);
            $this->fail('Should reject');
        } catch (ValidationException) {
        }
        $this->assertEquals(300, $beef->fresh()->quantity);
        $kit->update(['quantity' => 2]);
        $service->handle($h, $meal->id);
        $this->assertEquals(0, $kit->fresh()->quantity);
        $this->assertEquals(150, $beef->fresh()->quantity);
    }

    public function test_expired_stock_and_planning_placeholders_cannot_be_cooked(): void
    {
        $h = $this->household();
        $meal = $this->meal($h, [['name' => 'beef', 'quantity' => 100, 'unit' => 'g']]);
        $stock = $this->stock($h, 'beef', 500, 'g', today()->subDay()->toDateString());
        $this->actingAs($this->user($h))->post('/meals/'.$meal->id.'/cook')->assertSessionHasErrors('stock');
        $stock->update(['expires_at' => null]);
        $meal->recipe->update(['planning_only' => true]);
        $this->post('/meals/'.$meal->id.'/cook')->assertSessionHasErrors('stock');
        $this->assertEquals(500, $stock->fresh()->quantity);
    }

    public function test_batch_prep_requires_verified_empty_drawer(): void
    {
        $h = $this->household();
        $item = $this->stock($h, 'peas', null);
        $drawer = $item->location;
        $this->assertFalse($drawer->isConfirmedEmpty());
        $this->actingAs($this->user($h))->post('/locations/'.$drawer->id.'/verify')->assertSessionHasErrors('quantity');
        $item->update(['quantity' => 0]);
        $this->post('/locations/'.$drawer->id.'/verify')->assertSessionHasNoErrors();
        $this->assertTrue($drawer->fresh()->isConfirmedEmpty());
        $item->update(['quantity' => 1]);
        $this->assertFalse($drawer->fresh()->isConfirmedEmpty());
    }

    public function test_invitation_joins_existing_household_and_login_logout_work(): void
    {
        $h = $this->household();
        $owner = $this->user($h);
        $code = Str::random(32);
        $h->update(['join_code' => hash('sha256', $code)]);
        $this->post('/register', ['name' => 'Member', 'email' => 'member@example.test', 'password' => 'GoodPassword123!', 'password_confirmation' => 'GoodPassword123!', 'join_code' => $code])->assertRedirect('/');
        $member = User::where('email', 'member@example.test')->first();
        $this->assertEquals($h->id, $member->household_id);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/login', ['email' => 'member@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'member@example.test', 'password' => 'GoodPassword123!'])->assertRedirect('/');
        $this->assertAuthenticatedAs($member);
    }

    public function test_invalid_invitation_does_not_create_account(): void
    {
        $this->post('/register', ['name' => 'Member', 'email' => 'member@example.test', 'password' => 'GoodPassword123!', 'password_confirmation' => 'GoodPassword123!', 'join_code' => str_repeat('x', 32)])->assertSessionHasErrors('join_code');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('households', 0);
    }

    public function test_negative_quantities_are_rejected_and_blank_stock_is_unverified(): void
    {
        $h = $this->household();
        $location = $h->locations()->create(['name' => 'Fridge', 'type' => 'fridge']);
        $this->actingAs($this->user($h));
        $data = ['name' => ' PEAS ', 'quantity' => -1, 'unit' => 'g', 'minimum_quantity' => 0, 'storage_location_id' => $location->id];
        $this->post('/inventory', $data)->assertSessionHasErrors('quantity');
        $data['quantity'] = '';
        $this->post('/inventory', $data)->assertSessionHasNoErrors();
        $this->assertNull($h->inventory()->first()->quantity);
        $this->assertSame('peas', $h->inventory()->first()->name);
    }

    public function test_recipe_parser_and_duplicate_kit_counts_work(): void
    {
        $h = $this->household();
        $this->actingAs($this->user($h));
        $this->post('/recipes', ['name' => 'Lunch', 'servings' => 2, 'ingredients' => 'peas | 200 | kg'])->assertSessionHasErrors('ingredients');
        $this->post('/recipes', ['name' => 'Lunch', 'servings' => 2, 'ingredients' => "peas | 200 | g\nbeef | 300 | g"])->assertRedirect('/recipes');
        $this->assertSame(2, $h->recipes()->first()->ingredients()->count());
        $this->post('/kits', ['name' => ' Mild Beef ', 'brand' => 'SimplyCook', 'quantity' => 2])->assertSessionHasNoErrors();
        $this->post('/kits', ['name' => 'mild beef', 'brand' => 'simplycook', 'quantity' => 3])->assertSessionHasNoErrors();
        $this->assertSame(1, $h->kits()->count());
        $this->assertSame(3, $h->kits()->first()->quantity);
    }

    public function test_cooked_meals_are_excluded_from_shopping_and_cannot_be_replaced(): void
    {
        $h = $this->household();
        $meal = $this->meal($h, [['name' => 'peas', 'quantity' => 100, 'unit' => 'g']]);
        $this->stock($h, 'peas', 100);
        app(CookMeal::class)->handle($h, $meal->id);
        $this->assertSame([], app(ShoppingList::class)->forWeek($h, CarbonImmutable::parse('2026-10-19'))['rows']);
        $this->actingAs($this->user($h))->post('/meals', ['recipe_id' => $meal->recipe_id, 'date' => '2026-10-20', 'slot' => 'late_dinner', 'audience' => 'Adults', 'servings' => 2])->assertSessionHasErrors('date');
        $this->delete('/meals/'.$meal->id)->assertSessionHasErrors('meal');
    }

    public function test_storage_setup_can_be_renamed_and_unused_drawers_removed_safely(): void
    {
        $h = $this->household();
        $other = $this->household('Other');
        $empty = $h->locations()->create(['name' => 'Unused drawer', 'type' => 'freezer']);
        $foreign = $other->locations()->create(['name' => 'Private drawer', 'type' => 'freezer']);
        $stock = $this->stock($h, 'peas', 100);
        $this->actingAs($this->user($h));
        $this->put('/locations/'.$empty->id, ['name' => 'Top drawer'])->assertSessionHasNoErrors();
        $this->assertSame('Top drawer', $empty->fresh()->name);
        $this->put('/locations/'.$foreign->id, ['name' => 'Changed'])->assertNotFound();
        $this->delete('/locations/'.$foreign->id)->assertNotFound();
        $this->delete('/locations/'.$stock->storage_location_id)->assertSessionHasErrors('location');
        $this->delete('/locations/'.$empty->id)->assertRedirect();
        $this->assertDatabaseMissing('storage_locations', ['id' => $empty->id]);
    }

    public function test_email_case_is_normalized_before_registration_uniqueness_check(): void
    {
        $h = $this->household();
        User::factory()->create(['email' => 'member@example.test', 'household_id' => $h->id]);
        $this->post('/register', ['name' => 'Duplicate', 'email' => 'MEMBER@example.test', 'password' => 'GoodPassword123!', 'password_confirmation' => 'GoodPassword123!'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    }
}

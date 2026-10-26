<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use App\Services\MealIngredientExchange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShoppingExchangeTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $household = Household::create(['name' => 'Import test', 'join_code' => bin2hex(random_bytes(32)), 'plan_starts_on' => '2026-10-20']);

        return User::factory()->create(['household_id' => $household->id]);
    }

    private function exchangeDocument(): string
    {
        return file_get_contents(resource_path('import-examples/meal-ingredients.json'));
    }

    private function prepare(array $extra = [])
    {
        return $this->post('/shopping/import/preview', array_merge(['week' => '2026-10-20', 'format' => 'auto', 'exchange_text' => $this->exchangeDocument()], $extra));
    }

    public function test_real_supplied_json_preserves_stock_uncertainty_units_and_servings(): void
    {
        $payload = app(MealIngredientExchange::class)->parse($this->exchangeDocument());
        $this->assertCount(19, $payload['ingredients']);
        $this->assertCount(2, $payload['recipes']);
        $this->assertSame([4, 4], array_column($payload['recipes'], 'servings'));
        $rows = collect($payload['ingredients'])->keyBy('ingredient_id');
        $this->assertSame(300.0, $rows['cooked_prawns']['quantity']);
        $this->assertNull($rows['cooked_prawns']['inventory_quantity']);
        $this->assertSame(250.0, $rows['cooked_prawns']['reported_inventory_min']);
        $this->assertSame(300.0, $rows['cooked_prawns']['reported_inventory_max']);
        $this->assertFalse($rows['cooked_prawns']['inventory_quantity_confirmed']);
        $this->assertNull($rows['salt']['quantity']);
        $this->assertSame('to taste', $rows['salt']['unit']);
        $this->assertSame('tsp', $rows['paprika']['unit']);
        $this->assertSame('whole', $rows['onion']['unit']);
        $this->assertSame('nest', $rows['meringue']['unit']);
        $this->assertTrue(collect($payload['ingredients'])->every(fn ($row) => $row['purchase_quantity'] === null));
    }

    public function test_json_and_csv_have_the_same_semantic_fingerprint_even_when_reordered(): void
    {
        $parser = app(MealIngredientExchange::class);
        $csv = file_get_contents(resource_path('import-examples/meal-ingredients.csv'));
        $json = json_decode($this->exchangeDocument(), true);
        $json['ingredients'] = array_reverse($json['ingredients']);
        $this->assertSame($parser->fingerprint($parser->parse(json_encode($json))), $parser->fingerprint($parser->parse("\xEF\xBB\xBF".$csv, 'csv')));
    }

    public function test_preview_then_confirm_saves_only_requirements_and_never_stock_or_purchases(): void
    {
        Http::preventStrayRequests();
        $user = $this->user();
        $location = $user->household->locations()->create(['name' => 'Freezer', 'type' => 'freezer']);
        $stock = $user->household->inventory()->create(['name' => 'frozen cooked king prawns', 'quantity' => 123, 'unit' => 'g', 'storage_location_id' => $location->id]);
        $this->actingAs($user)->get('/shopping/import?week=2026-10-20')->assertOk()->assertSee('CSV or JSON');
        $this->get('/shopping/import/example/json')->assertDownload('meal-ingredients.json');
        $this->get('/shopping/import/example/csv')->assertDownload('meal-ingredients.csv');
        $preview = $this->prepare()->assertRedirect()->headers->get('Location');
        $this->assertDatabaseCount('meal_ingredient_imports', 0);
        $this->get($preview)->assertOk()->assertSee('Review ingredients')->assertSee('250–300')->assertSee('not calculated or confirmed');
        $this->post($preview.'/save', ['reviewed' => 1])->assertRedirect('/shopping?week=2026-10-20');
        $this->assertDatabaseCount('meal_ingredient_imports', 1);
        $this->assertEquals(123, $stock->fresh()->quantity);
        $this->assertDatabaseCount('inventory_items', 1);
        foreach (['stock_movements', 'purchase_batches', 'shopping_extras', 'planned_meals', 'recipes', 'recipe_ingredients'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->get('/shopping?week=2026-10-20')->assertOk()->assertSee('Imported meal ingredients')->assertSee('Strawberry Eton Mess')->assertSee('To taste')->assertSee('Reported estimate: 250–300');
        $this->get('/shopping?week=2026-11-01')->assertOk()->assertDontSee('Imported meal ingredients');
        Http::assertNothingSent();
    }

    public function test_exact_reimports_and_repeated_save_clicks_are_idempotent_across_formats(): void
    {
        $this->actingAs($this->user());
        $first = $this->prepare()->headers->get('Location');
        $this->post($first.'/save', ['reviewed' => 1])->assertRedirect();
        $this->post($first.'/save', ['reviewed' => 1])->assertRedirect()->assertSessionHas('status', 'This meal ingredient file is already imported. No duplicates were added.');
        $second = $this->prepare(['week' => '2026-10-27', 'exchange_text' => '', 'exchange_file' => UploadedFile::fake()->createWithContent('ingredients.csv', file_get_contents(resource_path('import-examples/meal-ingredients.csv')))])->headers->get('Location');
        $this->get($second)->assertOk()->assertSee('already saved');
        $this->post($second.'/save', ['reviewed' => 1])->assertRedirect('/shopping?week=2026-10-20');
        $this->assertDatabaseCount('meal_ingredient_imports', 1);
    }

    public function test_changed_meal_requires_explicit_overlap_review(): void
    {
        $this->actingAs($this->user());
        $first = $this->prepare()->headers->get('Location');
        $this->post($first.'/save', ['reviewed' => 1]);
        $payload = json_decode($this->exchangeDocument(), true);
        $payload['ingredients'][0]['quantity'] = 500;
        $second = $this->prepare(['exchange_text' => json_encode($payload)])->headers->get('Location');
        $this->get($second)->assertSee('already have imported ingredients')->assertSee('separate copy');
        $this->post($second.'/save', ['reviewed' => 1])->assertSessionHasErrors('allow_overlap');
        $this->assertDatabaseCount('meal_ingredient_imports', 1);
        $this->post($second.'/save', ['reviewed' => 1, 'allow_overlap' => 1])->assertRedirect();
        $this->assertDatabaseCount('meal_ingredient_imports', 2);
    }

    public function test_household_boundaries_confirmation_and_preview_expiry_are_enforced(): void
    {
        $this->get('/shopping/import')->assertRedirect('/login');
        $owner = $this->user();
        $this->actingAs($owner);
        $preview = $this->prepare()->headers->get('Location');
        $this->post($preview.'/save')->assertSessionHasErrors('reviewed');
        $this->actingAs($this->user())->get($preview)->assertNotFound();
        $this->post($preview.'/save', ['reviewed' => 1])->assertNotFound();
        $this->actingAs($owner)->post($preview.'/save', ['reviewed' => 1])->assertRedirect();
        $this->actingAs($this->user())->get('/shopping?week=2026-10-20')->assertDontSee('Strawberry Eton Mess');
        $this->actingAs($owner);
        $this->travel(31)->minutes();
        $this->get($preview)->assertNotFound();
        $this->post($preview.'/save', ['reviewed' => 1])->assertNotFound();
        $this->travelBack();
    }

    public function test_invalid_data_is_rejected_atomically_with_useful_errors(): void
    {
        $this->actingAs($this->user());
        $this->prepare(['exchange_text' => '{broken'])->assertSessionHasErrors('exchange');
        $this->prepare(['exchange_text' => "ingredient_name,quantity\nRice,300"])->assertSessionHasErrors('exchange');
        $base = json_decode($this->exchangeDocument(), true);
        $invalid = [];
        $version = $base;
        $version['contract_version'] = '2.0';
        $invalid[] = $version;
        $negative = $base;
        $negative['ingredients'][0]['quantity'] = -2;
        $invalid[] = $negative;
        $date = $base;
        $date['ingredients'][0]['meal_date'] = '2026-02-30';
        $invalid[] = $date;
        $range = $base;
        $range['ingredients'][2]['reported_inventory_min'] = 350;
        $invalid[] = $range;
        $null = $base;
        $null['ingredients'][0]['quantity'] = null;
        $invalid[] = $null;
        $boolean = $base;
        $boolean['ingredients'][0]['inventory_quantity_confirmed'] = 'false';
        $invalid[] = $boolean;
        $inventory = $base;
        $inventory['ingredients'][0]['inventory_quantity'] = 400;
        $invalid[] = $inventory;
        $duplicate = $base;
        $duplicate['ingredients'][] = $duplicate['ingredients'][0];
        $invalid[] = $duplicate;
        $servings = $base;
        $servings['ingredients'][0]['servings'] = 8;
        $invalid[] = $servings;
        foreach ($invalid as $document) {
            $response = $this->prepare(['exchange_text' => json_encode($document)]);
            $response->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('meal_ingredient_imports', 0);
        $this->assertDatabaseCount('inventory_items', 0);
    }

    public function test_file_limits_and_ambiguous_inputs_are_rejected(): void
    {
        $this->actingAs($this->user());
        $this->prepare(['exchange_file' => UploadedFile::fake()->create('large.json', 513)])->assertSessionHasErrors('exchange_file');
        $this->prepare(['exchange_file' => UploadedFile::fake()->createWithContent('both.json', $this->exchangeDocument())])->assertSessionHasErrors('exchange');
        $this->prepare(['exchange_text' => ''])->assertSessionHasErrors('exchange');
        $this->prepare(['week' => 'not-a-date'])->assertSessionHasErrors('week');
        $this->get('/shopping/import?week=not-a-date')->assertSessionHasErrors('week');
        $this->assertDatabaseCount('meal_ingredient_imports', 0);
    }

    public function test_import_text_is_escaped_and_unknown_fields_cannot_write_application_data(): void
    {
        $this->actingAs($this->user());
        $payload = json_decode($this->exchangeDocument(), true);
        $payload['ingredients'][0]['notes'] = '<script>alert("bad")</script>';
        $preview = $this->prepare(['exchange_text' => json_encode($payload)])->headers->get('Location');
        $this->get($preview)->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("bad")</script>', false);
        $payload['ingredients'][0]['household_id'] = 999;
        $this->prepare(['exchange_text' => json_encode($payload)])->assertSessionHasErrors('exchange');
    }

    public function test_csv_quoted_commas_multiline_notes_and_false_survive_parsing(): void
    {
        $stream = fopen('php://temp', 'r+');
        $document = json_decode($this->exchangeDocument(), true);
        fputcsv($stream, MealIngredientExchange::FIELDS, ',', '"', '');
        $row = $document['ingredients'][0];
        $row['notes'] = "First, check the cupboard.\nThen review.";
        $values = array_map(fn ($field) => $row[$field] === false ? 'false' : $row[$field], MealIngredientExchange::FIELDS);
        fputcsv($stream, $values, ',', '"', '');
        rewind($stream);
        $payload = app(MealIngredientExchange::class)->parse(stream_get_contents($stream), 'csv');
        fclose($stream);
        $this->assertSame($row['notes'], $payload['ingredients'][0]['notes']);
        $this->assertSame('Chicken thigh, boneless', $payload['ingredients'][0]['ingredient_name']);
        $this->assertFalse($payload['ingredients'][0]['inventory_quantity_confirmed']);
        $this->assertNull($payload['ingredients'][0]['purchase_quantity']);
    }

    public function test_import_can_be_removed_only_by_its_household_without_stock_changes(): void
    {
        $owner = $this->user();
        $this->actingAs($owner);
        $preview = $this->prepare()->headers->get('Location');
        $this->post($preview.'/save', ['reviewed' => 1]);
        $id = DB::table('meal_ingredient_imports')->value('id');
        $this->actingAs($this->user())->delete('/shopping/import/'.$id)->assertNotFound();
        $this->assertDatabaseCount('meal_ingredient_imports', 1);
        $this->actingAs($owner)->delete('/shopping/import/'.$id)->assertRedirect('/shopping?week=2026-10-20');
        $this->assertDatabaseCount('meal_ingredient_imports', 0);
        $this->assertDatabaseCount('inventory_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('purchase_batches', 0);
        $this->post($preview.'/save', ['reviewed' => 1])->assertRedirect();
        $this->assertDatabaseCount('meal_ingredient_imports', 1);
    }
}

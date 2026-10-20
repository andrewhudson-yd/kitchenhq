<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use App\Services\DailyShopping;
use App\Services\PurchaseReconciliation;
use App\Services\SimpleStock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurchaseReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function kitchen(): User
    {
        config(['services.openai.key' => null]);
        $h = Household::create(['name' => 'Test', 'join_code' => bin2hex(random_bytes(32))]);
        $location = $h->locations()->create(['name' => 'Cupboard', 'type' => 'pantry']);
        $h->inventory()->create(['name' => 'Coffee', 'qualitative_stock' => 'out', 'quantity' => 0, 'unit' => 'g', 'storage_location_id' => $location->id]);
        DB::table('shopping_extras')->insert(['household_id' => $h->id, 'week' => '2026-10-20', 'name' => 'Milk', 'item_key' => hash('sha256', 'milk'), 'source' => 'manual']);
        return User::factory()->create(['household_id' => $h->id]);
    }

    private function photo()
    {
        return UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j0uoAAAAASUVORK5CYII='));
    }

    private function rows(int $id): array
    {
        return array_map(fn ($row) => $row + ['include' => 1], json_decode(DB::table('purchase_batches')->where('id', $id)->value('rows'), true));
    }

    public function test_receipt_and_ticks_reconcile_without_duplicate_stock_and_save_marks_list(): void
    {
        Storage::fake('local');
        $u = $this->kitchen();
        $this->assertSame('Coffee', PurchaseReconciliation::name('JS COFFEE 200g'));
        $this->actingAs($u)->post('/shopping/check', ['week' => '2026-10-20', 'item_key' => hash('sha256', 'coffee'), 'checked' => 1]);
        $this->post('/shopping/photo', ['purpose' => 'receipt', 'week' => '2026-10-20', 'photo' => $this->photo()])->assertRedirect('/shopping/photo/1');
        $this->post('/shopping/photo/1/purchases', ['items' => "JS COFFEE 200g\nCoffee\nMilk\nApples"])->assertRedirect('/shopping/purchases/1');
        $this->assertCount(3, $this->rows(1));
        $this->assertDatabaseCount('inventory_items', 1);
        $this->get('/shopping/purchases/1')->assertOk()->assertSee('Receipt + picked up');
        $this->post('/shopping/purchases/1/save', ['rows' => $this->rows(1)])->assertSessionHasNoErrors()->assertRedirect('/shopping?week=2026-10-20');
        $this->assertDatabaseCount('inventory_items', 3);
        $coffee = $u->household->inventory()->where('name', 'Coffee')->firstOrFail();
        $this->assertNull($coffee->quantity);
        $this->assertSame('plenty', $coffee->qualitative_stock);
        $this->assertDatabaseHas('shopping_checks', ['household_id' => $u->household_id, 'item_key' => hash('sha256', 'milk'), 'checked' => 1]);
        $list = app(DailyShopping::class)->forWeek($u->household, CarbonImmutable::parse('2026-10-20'));
        $this->assertCount(0, $list['groups']['buy']);
        $this->assertNotNull(DB::table('shopping_photo_drafts')->first()->imported_at);
        $this->post('/shopping/purchases/1/save', ['rows' => $this->rows(1)])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('inventory_items', 3);
        $this->post('/shopping/photo', ['purpose' => 'receipt', 'week' => '2026-10-20', 'photo' => $this->photo()])->assertRedirect('/shopping/photo/1');
        $this->assertDatabaseCount('shopping_photo_drafts', 1);
    }

    public function test_receipt_automatically_reads_friendly_names_without_saving_stock(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $u = $this->kitchen();
        config(['services.openai.key' => 'sk-test-not-real']);
        $response = ['output' => [['content' => [['type' => 'output_text', 'text' => '{"items":["JS COFFEE 200g","Coffee","Milk"]}']]]]];
        Http::fake(['api.openai.com/v1/responses' => Http::response($response)]);
        $this->actingAs($u)->post('/shopping/photo', ['purpose' => 'receipt', 'week' => '2026-10-20', 'photo' => $this->photo()])->assertRedirect('/shopping/purchases/1')->assertSessionHasNoErrors();
        $this->assertSame(['Coffee', 'Milk'], json_decode(DB::table('shopping_photo_drafts')->first()->items, true));
        $this->assertDatabaseCount('inventory_items', 1);
        Http::assertSent(fn ($request) => str_contains($request['instructions'], 'JS COFFEE 200g becomes Coffee') && $request['store'] === false);
    }

    public function test_picked_items_can_be_saved_without_receipt_and_cross_household_writes_fail(): void
    {
        $u = $this->kitchen();
        $this->actingAs($u)->post('/shopping/check', ['week' => '2026-10-20', 'item_key' => hash('sha256', 'coffee'), 'checked' => 1]);
        $this->post('/shopping/purchases/review', ['week' => '2026-10-20'])->assertRedirect('/shopping/purchases/1');
        $foreign = $this->kitchen();
        $this->actingAs($foreign)->get('/shopping/purchases/1')->assertNotFound();
        $this->post('/shopping/purchases/1/save', ['rows' => $this->rows(1)])->assertNotFound();
        $rows = $this->rows(1);
        $rows[0]['inventory_id'] = $foreign->household->inventory()->first()->id;
        $this->actingAs($u)->post('/shopping/purchases/1/save', ['rows' => $rows])->assertSessionHasErrors('rows.0.inventory_id');
        $this->assertSame('out', $u->household->inventory()->first()->qualitative_stock);
        $this->post('/shopping/purchases/1/save', ['rows' => $this->rows(1)])->assertSessionHasNoErrors();
        $this->assertSame('plenty', $u->household->inventory()->first()->qualitative_stock);
    }

    public function test_confirmed_product_match_is_remembered_and_not_saved_twice(): void
    {
        $u = $this->kitchen();
        $service = app(PurchaseReconciliation::class);
        $batchId = $service->prepare($u->household, '2026-10-20', ['Instant coffee']);
        $rows = $this->rows($batchId);
        $rows[0]['inventory_id'] = $u->household->inventory()->first()->id;
        $service->save($u->household, $batchId, $rows);
        $this->assertDatabaseCount('inventory_items', 1);
        $next = $service->prepare($u->household, '2026-10-27', ['Instant coffee', 'Coffee']);
        $this->assertCount(1, $this->rows($next));
        $this->assertEquals($rows[0]['inventory_id'], $this->rows($next)[0]['inventory_id']);
        $this->assertEquals(0, $service->save($u->household, $batchId, $rows));
    }
}

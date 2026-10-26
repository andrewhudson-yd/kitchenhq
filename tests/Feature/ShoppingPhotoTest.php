<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use App\Services\DailyShopping;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShoppingPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openai.key' => null]);
    }

    private function user()
    {
        $h = Household::create(['name' => 'Photo kitchen', 'join_code' => bin2hex(random_bytes(32))]);

        return User::factory()->create(['household_id' => $h->id]);
    }

    private function photoId(): int
    {
        return (int) DB::table('shopping_photo_drafts')->orderByDesc('id')->value('id');
    }

    private function photo()
    {
        return UploadedFile::fake()->createWithContent('list.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j0uoAAAAASUVORK5CYII='));
    }

    public function test_photo_can_be_saved_privately_without_ai_and_reviewed_before_import(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $u = $this->user();
        $this->actingAs($u)->get('/shopping/photo')->assertOk()->assertSee('Choose a photo');
        $this->post('/shopping/photo', ['week' => '2026-10-20', 'photo' => $this->photo()])->assertRedirect('/shopping/photo/'.$this->photoId());
        $draft = DB::table('shopping_photo_drafts')->first();
        Storage::disk('local')->assertExists($draft->path);
        $this->assertDatabaseCount('shopping_extras', 0);
        $this->get('/shopping/photo/'.$this->photoId())->assertOk()->assertSee('Check your list');
        $this->get('/shopping/photo/'.$this->photoId().'/image')->assertOk();
        $this->post('/shopping/photo/'.$this->photoId().'/import', ['items' => "Milk\nMilk\n2 apples"])->assertRedirect('/shopping?week=2026-10-20');
        $this->post('/shopping/photo/'.$this->photoId().'/import', ['items' => "Milk\nMilk\n2 apples"])->assertRedirect();
        $this->assertDatabaseCount('shopping_extras', 2);
        $this->assertDatabaseCount('inventory_items', 0);
        $list = app(DailyShopping::class)->forWeek($u->household, CarbonImmutable::parse('2026-10-20'));
        $this->assertEqualsCanonicalizing(['Milk', '2 apples'], collect($list['groups']['buy'])->pluck('name')->all());
        Http::assertNothingSent();
        $other = $this->user();
        $this->actingAs($other)->get('/shopping/photo/'.$this->photoId())->assertNotFound();
        $this->get('/shopping/photo/'.$this->photoId().'/image')->assertNotFound();
        $this->post('/shopping/photo/'.$this->photoId().'/import', ['items' => 'Private'])->assertNotFound();
    }

    public function test_reading_uses_image_input_and_never_adds_items_without_review(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        $u = $this->user();
        config(['services.openai.key' => 'sk-fake-test-key-not-live-12345']);
        $this->actingAs($u)->post('/shopping/photo', ['week' => '2026-10-20', 'photo' => $this->photo()]);
        $output = ['output' => [['content' => [['type' => 'output_text', 'text' => '{"items":["Bread","[unclear]"]}']]]]];
        Http::fake(['api.openai.com/v1/responses' => Http::response($output)]);
        $this->post('/shopping/photo/'.$this->photoId().'/read')->assertSessionHasNoErrors();
        Http::assertSent(fn ($r) => $r['store'] === false && $r['input'][0]['content'][1]['type'] === 'input_image' && str_starts_with($r['input'][0]['content'][1]['image_url'], 'data:image/png;base64,'));
        $this->assertDatabaseCount('shopping_extras', 0);
        $this->get('/shopping/photo/'.$this->photoId())->assertSee('Bread')->assertSee('[unclear]');
        $this->post('/shopping/photo/'.$this->photoId().'/import', ['items' => "Bread\n[unclear]"])->assertSessionHasErrors('items');
        $this->assertDatabaseCount('shopping_extras', 0);
    }

    public function test_upload_errors_and_api_failure_preserve_data(): void
    {
        Storage::fake('local');
        $u = $this->user();
        $this->actingAs($u)->post('/shopping/photo', ['week' => '2026-10-20', 'photo' => UploadedFile::fake()->create('bad.txt', 2)])->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('shopping_photo_drafts', 0);
        $this->post('/shopping/photo', ['week' => '2026-10-20', 'photo' => $this->photo()]);
        $this->post('/shopping/photo/'.$this->photoId().'/read')->assertSessionHasErrors('photo');
        config(['services.openai.key' => 'sk-fake-test-key-not-live-12345']);
        Http::fake(['api.openai.com/v1/responses' => Http::response([], 429)]);
        $this->post('/shopping/photo/'.$this->photoId().'/read')->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('shopping_extras', 0);
        Storage::disk('local')->assertExists(DB::table('shopping_photo_drafts')->first()->path);
    }
}

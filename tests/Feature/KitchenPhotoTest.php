<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KitchenPhotoTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $h = Household::create(['name' => 'Photo kitchen', 'join_code' => bin2hex(random_bytes(32))]);
        $fridge = $h->locations()->create(['name' => 'Fridge', 'type' => 'fridge']);
        $h->inventory()->create(['name' => 'Cheddar cheese', 'storage_location_id' => $fridge->id, 'unit' => 'each', 'qualitative_stock' => 'out', 'quantity' => 0]);

        return User::factory()->create(['household_id' => $h->id]);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('fridge.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j0uoAAAAASUVORK5CYII='));
    }

    private function reply(array $items): array
    {
        return ['output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['items' => $items])]]]]];
    }

    private function fakeAi(array ...$replies): void
    {
        $sequence = Http::sequence();
        foreach ($replies as $items) {
            $sequence->push($this->reply($items));
        }
        Http::fake(['api.openai.com/*' => $sequence]);
    }

    public function test_photo_items_are_reviewed_then_added_to_the_kitchen(): void
    {
        config(['services.openai.key' => 'test-key']);
        $this->fakeAi([['name' => 'Semi-skimmed milk', 'place' => 'fridge'], ['name' => 'Cheddar cheese', 'place' => 'fridge'], ['name' => 'Peas', 'place' => 'freezer'], ['name' => 'Ketchup', 'place' => 'somewhere']]);
        $u = $this->user();
        $h = $u->household;

        $this->actingAs($u)->post('/kitchen/photo', ['photo' => $this->photo(), 'place' => 'fridge'])->assertRedirect('/kitchen/photo');
        Http::assertSent(fn ($request) => str_contains(json_encode($request->data(), JSON_UNESCAPED_SLASHES), 'data:image/png;base64,') && str_contains(json_encode($request->data()), 'Cheddar cheese'));
        $this->get('/kitchen/photo')->assertOk()->assertSee('What we spotted')->assertSee('Semi-skimmed milk')->assertSee('Peas');

        $this->post('/kitchen/photo/add', ['items' => [
            ['add' => 1, 'name' => 'Semi-skimmed milk', 'place' => 'fridge'],
            ['add' => 1, 'name' => 'Cheddar cheese', 'place' => 'fridge'],
            ['add' => 1, 'name' => 'Frozen peas', 'place' => 'freezer'],
            ['name' => 'Ketchup', 'place' => 'fridge'],
        ]])->assertRedirect('/inventory?place=fridge')->assertSessionHas('status', '3 items added to the kitchen.');

        $this->assertSame('plenty', $h->inventory()->where('name', 'Semi-skimmed milk')->firstOrFail()->qualitative_stock);
        $this->assertSame('freezer', $h->inventory()->where('name', 'Frozen peas')->firstOrFail()->location->type);
        $this->assertSame(1, $h->inventory()->where('name', 'Cheddar cheese')->count());
        $this->assertSame('plenty', $h->inventory()->where('name', 'Cheddar cheese')->first()->qualitative_stock);
        $this->assertSame(0, $h->inventory()->where('name', 'Ketchup')->count());
        $this->get('/kitchen/photo')->assertRedirect('/inventory');
    }

    public function test_unknown_places_fall_back_to_the_tab_and_errors_are_friendly(): void
    {
        config(['services.openai.key' => 'test-key']);
        $u = $this->user();
        $this->fakeAi([['name' => 'Ketchup', 'place' => 'somewhere']], []);
        $this->actingAs($u)->post('/kitchen/photo', ['photo' => $this->photo(), 'place' => 'pantry']);
        $this->get('/kitchen/photo')->assertSee('<option value="pantry" selected>', false);

        $this->post('/kitchen/photo', ['photo' => $this->photo(), 'place' => 'fridge'])->assertRedirect('/inventory?place=fridge')->assertSessionHasErrors('photo');
        $this->post('/kitchen/photo', ['photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'), 'place' => 'fridge'])->assertSessionHasErrors('photo');
    }

    public function test_without_an_api_key_nothing_is_sent(): void
    {
        config(['services.openai.key' => null]);
        Http::preventStrayRequests();
        $this->actingAs($this->user())->post('/kitchen/photo', ['photo' => $this->photo(), 'place' => 'fridge'])->assertSessionHasErrors('photo');
        Http::assertNothingSent();
    }
}

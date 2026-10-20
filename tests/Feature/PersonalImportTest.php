<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PersonalHouseholdImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PersonalImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_preserves_unknowns_variants_and_later_edits(): void
    {
        $service = app(PersonalHouseholdImport::class);
        $hash = Hash::make('test-password-only');
        $u = $service->run('seed@example.test', $hash);
        $h = $u->household;
        $this->assertTrue(Hash::check('test-password-only', $u->password));
        $this->assertCount(3, $h->profiles);
        $this->assertCount(97, $h->inventory);
        $this->assertCount(28, $h->kits);
        $this->assertCount(30, $h->recipes);
        $beef = $h->inventory()->where('name', 'Beef mince')->firstOrFail();
        $this->assertNull($beef->quantity);
        $this->assertSame('Out of stock', $beef->stock_level);
        $this->assertSame('Unverified', $h->inventory()->where('name', 'Beef burgers')->firstOrFail()->stock_level);
        $parm = $h->recipes()->whereIn('name', ['Chicken Parmigiana', 'Aubergine Parmigiana'])->get();
        $this->assertCount(1, $parm->pluck('recipe_kit_id')->unique());
        $this->assertTrue($h->kits->every(fn ($k) => $k->quantity === null));
        $beef->update(['quantity' => 2]);
        $service->run('seed@example.test', $hash);
        $this->assertEquals(2, $beef->fresh()->quantity);
        $this->assertSame(1, User::count());
        $this->assertSame(97, $h->inventory()->count());
        $this->assertDatabaseCount('household_imports', 1);
        $other = $service->run('another@example.test', $hash);
        $this->assertNotEquals($h->id, $other->household_id);
    }
}

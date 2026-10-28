<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use App\Services\ShoppingActions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppShoppingTest extends TestCase
{
    use RefreshDatabase;

    private function household(): array
    {
        $h = Household::create(['name' => 'Our Kitchen', 'join_code' => bin2hex(random_bytes(32))]);
        $u = User::factory()->create(['household_id' => $h->id]);
        $h->locations()->create(['name' => 'Fridge', 'type' => 'fridge']);

        return [$h, $u];
    }

    private function token(User $u): string
    {
        return $this->actingAs($u)->postJson('/app/token', ['device' => 'Test phone'])->assertOk()->json('token');
    }

    public function test_token_requires_a_signed_in_user_and_only_its_hash_is_stored(): void
    {
        $this->postJson('/app/token')->assertUnauthorized();
        [, $u] = $this->household();
        $token = $this->token($u);
        $this->assertSame(48, strlen($token));
        $this->assertDatabaseHas('app_tokens', ['user_id' => $u->id, 'token_hash' => hash('sha256', $token), 'name' => 'Test phone']);
        $this->assertDatabaseMissing('app_tokens', ['token_hash' => $token]);
    }

    public function test_api_rejects_missing_or_unknown_tokens(): void
    {
        $this->getJson('/api/app/shopping')->assertUnauthorized();
        $this->withToken('not-a-real-token')->getJson('/api/app/shopping')->assertUnauthorized();
    }

    public function test_offline_operations_apply_like_the_website_and_return_the_fresh_list(): void
    {
        [$h, $u] = $this->household();
        $token = $this->token($u);
        $week = $h->currentWeek()->toDateString();

        $list = $this->withToken($token)->postJson('/api/app/shopping/sync', ['ops' => [
            ['id' => 'a1', 'type' => 'add', 'name' => '2 milk'],
            ['id' => 'a2', 'type' => 'add', 'name' => 'bread'],
            ['id' => 'a3', 'type' => 'add', 'name' => 'eggs'],
            ['id' => 'q1', 'type' => 'quantity', 'name' => 'bread', 'quantity' => '2 loaves', 'week' => $week],
            ['id' => 'g1', 'type' => 'got', 'name' => 'milk', 'value' => true, 'week' => $week],
            ['id' => 'r1', 'type' => 'remove', 'key' => ShoppingActions::key('eggs'), 'value' => true, 'week' => $week],
        ]])->assertOk();

        $this->assertSame(['a1', 'a2', 'a3', 'q1', 'g1', 'r1'], collect($list->json('results'))->where('ok', true)->pluck('id')->all());
        $items = collect($list->json('items'))->keyBy('name');
        $this->assertTrue($items['milk']['done'], 'ticked items stay on the list as done');
        $this->assertFalse($items['bread']['done']);
        $this->assertSame('2 loaves', $items['bread']['quantity']);
        $this->assertSame('Bakery', $items['bread']['section']);
        $this->assertArrayNotHasKey('eggs', $items->all(), 'removed items leave the list');
        $this->assertSame('plenty', $h->inventory()->where('name', 'milk')->value('qualitative_stock'), 'ticking restocks the kitchen');

        // The website shows the same state.
        $this->actingAs($u)->get('/shopping')->assertSee('Bread')->assertSee('2 loaves');

        // Replaying the same queue after a dropped connection changes nothing.
        $again = $this->withToken($token)->postJson('/api/app/shopping/sync', ['ops' => [
            ['id' => 'g1', 'type' => 'got', 'name' => 'milk', 'value' => true, 'week' => $week],
        ]])->assertOk();
        $this->assertEquals($list->json('items'), $again->json('items'));
    }

    public function test_bad_operations_are_reported_without_blocking_the_rest(): void
    {
        [$h, $u] = $this->household();
        $response = $this->withToken($this->token($u))->postJson('/api/app/shopping/sync', ['ops' => [
            ['id' => 'bad', 'type' => 'got', 'week' => 'not-a-date', 'name' => 'milk'],
            ['id' => 'nokey', 'type' => 'remove'],
            ['id' => 'good', 'type' => 'add', 'name' => 'apples'],
        ]])->assertOk();

        $results = collect($response->json('results'))->keyBy('id');
        $this->assertFalse($results['bad']['ok']);
        $this->assertFalse($results['nokey']['ok']);
        $this->assertTrue($results['good']['ok']);
        $this->assertContains('apples', collect($response->json('items'))->pluck('name'));
    }

    public function test_household_members_share_one_list_and_other_households_are_isolated(): void
    {
        [$h, $u] = $this->household();
        $partner = User::factory()->create(['household_id' => $h->id]);
        $this->withToken($this->token($u))->postJson('/api/app/shopping/sync', ['ops' => [['id' => 'x', 'type' => 'add', 'name' => 'coffee']]])->assertOk();

        $this->withToken($this->token($partner))->getJson('/api/app/shopping')->assertOk()->assertJsonFragment(['name' => 'coffee']);

        $other = Household::create(['name' => 'Next door', 'join_code' => bin2hex(random_bytes(32))]);
        $neighbour = User::factory()->create(['household_id' => $other->id]);
        $this->withToken($this->token($neighbour))->getJson('/api/app/shopping')->assertOk()->assertJsonMissing(['name' => 'coffee']);
    }

    public function test_signing_out_revokes_the_phone_token(): void
    {
        [, $u] = $this->household();
        $token = $this->token($u);
        $this->actingAs($u)->post('/logout')->assertRedirect('/login');
        $this->withToken($token)->getJson('/api/app/shopping')->assertUnauthorized();
    }

    public function test_api_allows_the_app_origin(): void
    {
        [, $u] = $this->household();
        $this->withToken($this->token($u))->withHeaders(['Origin' => 'capacitor://localhost'])->getJson('/api/app/shopping')
            ->assertOk()->assertHeader('Access-Control-Allow-Origin');
    }
}

<?php

namespace Tests\Feature;

use App\Services\DailyShopping;
use App\Services\PersonalHouseholdImport;
use App\Services\TodaySetup;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MealIdeasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-26 09:00');
        CarbonImmutable::setTestNow('2026-10-26 09:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function kitchen()
    {
        $user = app(PersonalHouseholdImport::class)->run('ideas@example.test', Hash::make('only-a-test-password'));
        app(TodaySetup::class)->apply($user->household);

        return $user->fresh();
    }

    private function fakeAi(array $meals): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['meals' => $meals])]]]]])]);
    }

    public function test_ai_suggests_meals_from_the_kitchen_and_chosen_ones_are_planned(): void
    {
        config(['services.openai.key' => 'test-key']);
        $u = $this->kitchen();
        $h = $u->household;
        $this->fakeAi([
            ['name' => 'Chicken fajitas', 'description' => 'Smoky chicken wraps.', 'day' => '2026-10-27', 'for' => 'Everyone', 'uses' => ['Chicken breast fillets'], 'needs' => ['Tortilla wraps', 'Peppers']],
            ['name' => 'Fish finger sandwiches', 'description' => 'Quick tea for Noah.', 'day' => '2026-10-27', 'for' => 'Noah', 'uses' => ['Fish fingers'], 'needs' => []],
            ['name' => 'Veggie chilli', 'day' => '2026-10-27', 'for' => 'Everyone', 'uses' => ['Mixed beans in mild chilli sauce'], 'needs' => ['Peppers']],
            ['name' => 'Mystery stew', 'day' => '2031-01-01', 'for' => 'Martians'],
        ]);

        $this->actingAs($u)->post('/planner/ideas', ['week' => '2026-10-27', 'note' => 'quick dinners please'])->assertRedirect('/planner/ideas');
        Http::assertSent(function ($request) {
            $body = json_encode($request->data());

            // OpenAI's JSON mode refuses requests whose input doesn't mention JSON.
            return str_contains(json_encode($request->data()['input']), 'Return JSON') && str_contains($body, 'quick dinners please') && str_contains($body, 'Crunchy peanut butter') && str_contains($body, 'Noah');
        });
        $page = $this->get('/planner/ideas')->assertOk()->assertSee('Chicken fajitas')->assertSee('Tortilla wraps')->getContent();
        // The first idea for each day and person is ticked; alternatives are not; bad days and people are corrected.
        $this->assertMatchesRegularExpression('/ideas\[0\]\[pick\]" value="1" checked/', $page);
        $this->assertMatchesRegularExpression('/ideas\[1\]\[pick\]" value="1" checked/', $page);
        $this->assertDoesNotMatchRegularExpression('/ideas\[2\]\[pick\]" value="1" checked/', $page);
        $this->assertMatchesRegularExpression('/ideas\[3\]\[for\]">.*?<option selected>Everyone/s', $page);

        $this->post('/planner/ideas/plan', ['shop' => 1, 'ideas' => [
            ['pick' => 1, 'day' => '2026-10-28', 'for' => 'Everyone'],
            ['pick' => 1, 'day' => '2026-10-27', 'for' => 'Noah'],
            ['day' => '2026-10-27', 'for' => 'Everyone'],
            ['day' => '2026-10-27', 'for' => 'Everyone'],
        ]])->assertRedirect('/planner?week=2026-10-27')->assertSessionHas('status', '2 meals planned and 2 ingredients added to the shopping list.');

        $fajitas = $h->meals()->with('recipe')->whereDate('date', '2026-10-28')->where('audience', 'Everyone')->firstOrFail();
        $this->assertSame('Chicken fajitas', $fajitas->recipe->name);
        $this->assertSame('late_dinner', $fajitas->slot);
        $this->assertContains('Tortilla wraps', $fajitas->recipe->shopping_ingredients);
        $this->assertSame('early_dinner', $h->meals()->whereDate('date', '2026-10-27')->where('audience', 'Noah')->firstOrFail()->slot);
        $this->assertSame(0, $h->recipes()->where('name', 'Veggie chilli')->count());
        $list = collect(app(DailyShopping::class)->forWeek($h, CarbonImmutable::parse('2026-10-27'))['groups']['buy'])->pluck('name');
        $this->assertTrue($list->contains('Tortilla wraps') && $list->contains('Peppers'));
        $this->get('/planner?week=2026-10-27')->assertSee('Chicken fajitas')->assertSee('Plan the week with AI');
        // On the last day of a week, the AI box plans next week instead.
        $this->get('/planner?week=2026-10-20')->assertSee('Plan next week with AI')->assertSee('name="week" value="2026-10-27"', false);
    }

    public function test_only_days_left_in_the_week_can_be_planned_and_errors_are_friendly(): void
    {
        config(['services.openai.key' => 'test-key']);
        $u = $this->kitchen();
        $this->fakeAi([['name' => 'Roast chicken', 'day' => '2026-10-21', 'for' => 'Everyone']]);
        // This week runs 20–26 Oct and today is the 26th, so only today is offered.
        $this->actingAs($u)->post('/planner/ideas', ['week' => '2026-10-20']);
        $page = $this->get('/planner/ideas')->getContent();
        $this->assertStringContainsString('value="2026-10-26" selected', $page);
        $this->assertStringNotContainsString('value="2026-10-21"', $page);
        $this->post('/planner/ideas/plan', ['ideas' => [['pick' => 1, 'day' => '2026-10-21', 'for' => 'Everyone']]])->assertSessionHasErrors('ideas.0.day');
        $this->post('/planner/ideas/plan', ['ideas' => [['day' => '2026-10-26', 'for' => 'Everyone']]])->assertSessionHasErrors('ideas');
    }

    public function test_a_garbled_ai_reply_shows_a_friendly_error(): void
    {
        config(['services.openai.key' => 'test-key']);
        Http::fake(['api.openai.com/*' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => 'not json']]]]])]);
        $this->actingAs($this->kitchen())->post('/planner/ideas', ['week' => '2026-10-27', 'note' => 'x'])
            ->assertRedirect('/planner?week=2026-10-27')->assertSessionHasErrors(['note' => 'Couldn\'t get meal ideas just now. Please try again.']);
    }

    public function test_without_an_api_key_nothing_is_sent(): void
    {
        config(['services.openai.key' => null]);
        Http::preventStrayRequests();
        $this->actingAs($this->kitchen())->post('/planner/ideas', ['week' => '2026-10-27'])->assertSessionHasErrors('note');
        Http::assertNothingSent();
    }

    public function test_openai_rejections_show_its_explanation(): void
    {
        config(['services.openai.key' => 'test-key']);
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => "'messages' must contain the word 'json' <b>in some form</b>.", 'type' => 'invalid_request_error']], 400)]);
        $this->actingAs($this->kitchen())->post('/planner/ideas', ['week' => '2026-10-27'])
            ->assertSessionHasErrors(['note' => "OpenAI rejected the request: 'messages' must contain the word 'json' in some form. (HTTP 400; invalid_request_error)"]);
    }

    public function test_meals_can_be_deleted_with_undo_and_saving_stays_on_that_week(): void
    {
        $u = $this->kitchen();
        $h = $u->household;
        $recipe = $h->recipes()->firstOrFail();
        $this->actingAs($u)->post('/meals', ['recipe_id' => $recipe->id, 'date' => '2026-10-29', 'slot' => 'late_dinner', 'audience' => 'Adults', 'servings' => 2])
            ->assertRedirect('/planner?week=2026-10-27');
        $meal = $h->meals()->whereDate('date', '2026-10-29')->where('audience', 'Adults')->firstOrFail();
        $this->get('/planner?week=2026-10-27')->assertSee('Add a meal')->assertSee('Ideas from AI')->assertSee('aria-label="Delete '.e($recipe->name), false);

        $this->from('/planner?week=2026-10-27')->delete('/meals/'.$meal->id)->assertRedirect('/planner?week=2026-10-27')
            ->assertSessionHas('meal_removed', ['name' => $recipe->name, 'recipe_id' => $recipe->id, 'slot' => 'late_dinner', 'audience' => 'Adults', 'servings' => 2, 'date' => '2026-10-29']);
        $this->assertNull($meal->fresh());
        $this->post('/meals', session('meal_removed'));
        $this->assertSame(1, $h->meals()->whereDate('date', '2026-10-29')->where('audience', 'Adults')->count());

        $cooked = $h->meals()->whereDate('date', '2026-10-29')->where('audience', 'Adults')->first();
        $cooked->update(['cooked_at' => now()]);
        $this->delete('/meals/'.$cooked->id)->assertSessionHasErrors('meal');
        $this->get('/planner?week=2026-10-27')->assertDontSee('aria-label="Delete '.e($recipe->name).' on Thursday"', false);
    }
}

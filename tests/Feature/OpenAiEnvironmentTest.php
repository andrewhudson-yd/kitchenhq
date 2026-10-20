<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use App\Services\OpenAiConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_handles_missing_key_authentication_billing_and_success_without_leaking_secrets(): void
    {
        Http::preventStrayRequests();
        config(['services.openai.key' => null]);
        $this->artisan('kitchen-hq:check-openai')->expectsOutputToContain('OPENAI_API_KEY is missing')->assertFailed();
        Http::assertNothingSent();
        config(['services.openai.key' => 'sk-config-test-only', 'services.openai.model' => 'gpt-4.1-mini']);
        Http::fake(['api.openai.com/v1/responses' => Http::sequence()->push(['error' => ['code' => 'insufficient_quota']], 429)->push(['error' => ['code' => 'invalid_api_key']], 401)->push(['status' => 'completed', 'output' => []], 200)]);
        $this->artisan('kitchen-hq:check-openai')->expectsOutputToContain('credits or spending allowance')->assertFailed();

        $this->artisan('kitchen-hq:check-openai')->expectsOutputToContain('OpenAI rejected OPENAI_API_KEY')->assertFailed();

        $this->artisan('kitchen-hq:check-openai')->expectsOutputToContain('connection passed')->assertSuccessful();
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer sk-config-test-only') && $r['model'] === 'gpt-4.1-mini' && $r['store'] === false);
        $error = OpenAiConnection::error(new \Illuminate\Http\Client\Response(new \GuzzleHttp\Psr7\Response(429, [], '{"error":{"code":"insufficient_quota","message":"sk-secret"}}')));
        $this->assertStringNotContainsString('sk-secret', $error);
    }

    public function test_database_key_is_not_used_and_web_forms_cannot_replace_server_key(): void
    {
        $h = Household::create(['name' => 'Test', 'join_code' => bin2hex(random_bytes(32)), 'ai_api_key' => 'sk-old-database-key']);
        $user = User::factory()->create(['household_id' => $h->id]);
        config(['services.openai.key' => 'sk-env-only-test', 'services.openai.model' => 'gpt-4.1-mini']);
        $this->assertSame('sk-env-only-test', OpenAiConnection::key());
        $this->actingAs($user)->get('/shopping/photo')->assertOk()->assertDontSee('sk-env-only-test')->assertDontSee('name="ai_api_key"', false);
        $this->post('/shopping/photo/connection', ['ai_api_key' => 'sk-attempted-replacement'])->assertSessionHasErrors('photo');
        $this->assertSame('sk-old-database-key', $h->fresh()->ai_api_key);
        $output = ['output' => [['content' => [['type' => 'output_text', 'text' => 'Ready.']]]]];
        Http::fake(['api.openai.com/v1/responses' => Http::response($output)]);
        $this->post('/assistant/ask', ['question' => 'Help with dinner'])->assertSessionHas('kitchen_ai_answer', 'Ready.');
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer sk-env-only-test'));
    }
}

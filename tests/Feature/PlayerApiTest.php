<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class PlayerApiTest extends TestCase
{
    use RefreshDatabase;
    private function raw(): string { return file_get_contents(base_path('tests/Fixtures/pull.json')); }
    private function bearer(string $token): array { return ['Authorization' => 'Bearer '.$token]; }
    public function test_game_shares_with_only_a_name(): void
    {
        $r = $this->postJson('/api/v1/players', ['name' => '  Fern  ']);
        $r->assertCreated()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('token_type', 'Bearer')->assertJsonPath('name', 'Fern');
        $token = $r->json('token');
        $user = User::where('name', 'Fern')->sole();
        $this->assertStringEndsWith('@players.missedit.invalid', $user->email);
        $up = $this->postJson('/api/v1/puzzles', ['puzzle_json' => $this->raw(), 'difficulty' => 'tricky'], $this->bearer($token));
        $up->assertCreated()->assertJsonPath('data.creator.name', 'Fern')->assertJsonPath('data.difficulty', 'tricky');
        $this->assertNotEmpty($up->json('data.share_url'));
        $this->getJson('/api/v1/puzzles')->assertOk()->assertJsonPath('data.0.creator.name', 'Fern')->assertJsonMissingPath('data.0.creator.email');
    }
    public function test_player_name_is_required_and_short(): void
    {
        $this->postJson('/api/v1/players', ['name' => '   '])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/api/v1/players', ['name' => str_repeat('a', 41)])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertSame(0, User::count());
    }
    public function test_renaming_changes_the_name_on_shared_puzzles(): void
    {
        $token = $this->postJson('/api/v1/players', ['name' => 'Fern'])->json('token');
        $this->postJson('/api/v1/puzzles', ['puzzle_json' => $this->raw(), 'difficulty' => 'gentle'], $this->bearer($token))->assertCreated();
        $this->patchJson('/api/v1/me', ['name' => 'Fern B'], $this->bearer($token))->assertOk()->assertJsonPath('data.name', 'Fern B');
        $this->getJson('/api/v1/puzzles')->assertJsonPath('data.0.creator.name', 'Fern B');
        $this->app['auth']->forgetGuards();
        $this->patchJson('/api/v1/me', ['name' => 'Someone'])->assertUnauthorized();
    }
    public function test_a_player_cannot_sign_in_on_the_website(): void
    {
        $this->postJson('/api/v1/players', ['name' => 'Fern'])->assertCreated();
        $user = User::sole();
        $this->post('/login', ['email' => $user->email, 'password' => ''])->assertSessionHasErrors();
        $this->assertGuest();
    }
    public function test_new_players_are_limited_per_connection(): void
    {
        for ($i = 0; $i < 3; $i++) $this->postJson('/api/v1/players', ['name' => "P$i"])->assertCreated();
        $this->postJson('/api/v1/players', ['name' => 'P3'])->assertTooManyRequests();
    }
}

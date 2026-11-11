<?php
namespace Tests\Feature;
use App\Models\Puzzle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
/** The read-only API the game's Puzzle Garden browser uses. */
class GardenApiTest extends TestCase {
    use RefreshDatabase;
    private function raw(string $title = 'Borrow the bend'): string {
        $d = json_decode(file_get_contents(base_path('tests/Fixtures/pull.json')), true);
        $d['Title'] = $title; $d['Id'] = bin2hex(random_bytes(16));
        return json_encode($d, JSON_PRETTY_PRINT);
    }
    private function publish(string $title, string $difficulty = 'tricky', ?User $user = null): Puzzle {
        $this->actingAs($user ?? User::factory()->create(['name' => 'Fern']))->post('/puzzles', ['puzzle_json' => $this->raw($title), 'difficulty' => $difficulty])->assertSessionHasNoErrors();
        auth()->logout();
        return Puzzle::latest('id')->firstOrFail();
    }
    public function test_list_returns_summaries_without_documents(): void {
        $p = $this->publish('Borrow the bend');
        $this->getJson('/api/v1/puzzles')->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('page', 1)->assertJsonPath('pages', 1)
            ->assertJsonPath('puzzles.0.id', $p->slug)->assertJsonPath('puzzles.0.title', 'Borrow the bend')
            ->assertJsonPath('puzzles.0.creator', 'Fern')->assertJsonPath('puzzles.0.difficulty', 'tricky')
            ->assertJsonPath('puzzles.0.mechanics', ['Gravity'])->assertJsonPath('puzzles.0.downloads', 0)
            ->assertJsonPath('puzzles.0.hasReplay', false)
            ->assertJsonMissingPath('puzzles.0.document')->assertJsonMissingPath('puzzles.0.user');
    }
    public function test_search_filters_sort_and_pages(): void {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); // 22 uploads in a row
        $fern = User::factory()->create(['name' => 'Fern']);
        $this->publish('Gentle start', 'gentle', $fern);
        $popular = $this->publish('Crowd favourite', 'expert', $fern);
        $popular->update(['downloads' => 9]);
        $this->getJson('/api/v1/puzzles?q=crowd')->assertJsonPath('total', 1)->assertJsonPath('puzzles.0.title', 'Crowd favourite');
        $this->getJson('/api/v1/puzzles?difficulty=gentle')->assertJsonPath('total', 1)->assertJsonPath('puzzles.0.title', 'Gentle start');
        $this->getJson('/api/v1/puzzles?sort=popular')->assertJsonPath('puzzles.0.title', 'Crowd favourite');
        $this->getJson('/api/v1/puzzles?sort=oldest')->assertJsonPath('puzzles.0.title', 'Gentle start');
        $this->getJson('/api/v1/puzzles?mechanic=Portals')->assertJsonPath('total', 0);
        for ($i = 0; $i < 20; $i++) $this->publish("Extra $i", 'tricky', $fern);
        $this->getJson('/api/v1/puzzles?page=2')->assertJsonPath('page', 2)->assertJsonPath('pages', 2)->assertJsonCount(2, 'puzzles');
    }
    public function test_bad_filters_are_json_errors(): void {
        $this->getJson('/api/v1/puzzles?sort=random')->assertStatus(422)->assertJsonValidationErrors('sort');
        $this->get('/api/v1/puzzles?page=0')->assertStatus(422)->assertJsonValidationErrors('page');
        $this->get('/api/v1/puzzles/not-a-puzzle')->assertNotFound()->assertHeader('Content-Type', 'application/json');
    }
    public function test_puzzle_is_exact_json_and_counts_a_download(): void {
        $p = $this->publish('Borrow the bend');
        $this->get('/api/v1/puzzles/'.$p->slug)->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8')->assertContent($p->document);
        $this->assertSame(1, $p->fresh()->downloads);
        $this->getJson('/api/v1/puzzles')->assertJsonPath('puzzles.0.downloads', 1);
    }
    public function test_api_is_read_only(): void {
        $this->postJson('/api/v1/puzzles', ['puzzle_json' => $this->raw(), 'difficulty' => 'gentle'])->assertStatus(405);
        $p = $this->publish('Borrow the bend');
        $this->deleteJson('/api/v1/puzzles/'.$p->slug)->assertStatus(405);
        $this->assertSame(1, Puzzle::count());
    }
}

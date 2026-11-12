<?php
namespace Tests\Feature;
use App\Models\Puzzle;
use App\Models\User;
use App\Services\PublishPuzzle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class PuzzleClearsTest extends TestCase
{
    use RefreshDatabase;
    private function puzzle(string $title): Puzzle
    {
        $d = json_decode(file_get_contents(base_path('tests/Fixtures/pull.json')), true);
        $d['Title'] = $title;
        return app(PublishPuzzle::class)->publish(User::factory()->create(), json_encode($d), 'gentle', null);
    }
    public function test_a_clear_counts_once_a_day_per_address(): void
    {
        $a = $this->puzzle('First');
        $b = $this->puzzle('Second');
        $this->postJson("/api/v1/puzzles/{$a->slug}/clears")->assertOk()->assertJsonPath('data.clears', 1);
        $this->postJson("/api/v1/puzzles/{$a->slug}/clears")->assertTooManyRequests();
        $this->postJson("/api/v1/puzzles/{$b->slug}/clears")->assertOk()->assertJsonPath('data.clears', 1);
        $this->assertSame(1, $a->fresh()->clears);
        $this->postJson('/api/v1/puzzles/00000000-0000-0000-0000-000000000000/clears')->assertNotFound();
    }
    public function test_clears_are_listed_and_sortable(): void
    {
        $a = $this->puzzle('Rarely cleared');
        $b = $this->puzzle('Often cleared');
        $b->forceFill(['clears' => 5])->save();
        $this->getJson('/api/v1/puzzles?sort=cleared')->assertOk()
            ->assertJsonPath('data.0.title', 'Often cleared')->assertJsonPath('data.0.clears', 5)
            ->assertJsonPath('data.1.clears', 0);
        $this->getJson("/api/v1/puzzles/{$a->slug}")->assertJsonPath('data.clears', 0);
        $this->get('/?sort=cleared')->assertOk()->assertSeeInOrder(['Often cleared', 'Rarely cleared']);
    }
}

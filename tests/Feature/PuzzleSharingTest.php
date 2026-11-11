<?php
namespace Tests\Feature;
use App\Models\Puzzle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
class PuzzleSharingTest extends TestCase {
    use RefreshDatabase;
    private function raw(): string { return file_get_contents(base_path('tests/Fixtures/pull.json')); }
    private function publish(array $extra = []): Puzzle {
        $this->actingAs(User::factory()->create())->post('/puzzles', array_merge(['puzzle_json'=>$this->raw(),'difficulty'=>'tricky'], $extra))->assertRedirect();
        return Puzzle::latest('id')->firstOrFail();
    }
    public function test_public_browsing_and_authentication_requirement(): void {
        $this->get('/')->assertOk()->assertSee('Every garden starts with a seed');
        $this->get('/puzzles/share')->assertRedirect('/login');
        $this->post('/puzzles', [])->assertRedirect('/login');
        $this->get('/how-to-play')->assertOk();
        $this->get('/puzzles/unknown')->assertNotFound();
    }
    public function test_publish_preview_and_byte_exact_download(): void {
        $puzzle = $this->publish(['notes'=>'A little nudge.']);
        $this->assertSame($this->raw(), $puzzle->document);
        $this->assertSame(['Gravity'], $puzzle->mechanics);
        $this->get(route('puzzles.show',$puzzle))->assertOk()->assertSee('Borrow the bend')->assertSee('A little nudge.')->assertSee('<svg',false);
        $this->get(route('puzzles.download',$puzzle))->assertOk()->assertHeader('Content-Type','application/json; charset=UTF-8')->assertContent($this->raw());
        $this->assertSame(1, $puzzle->fresh()->downloads);
    }
    public function test_upload_preserves_whitespace_and_replay(): void {
        $d = json_decode($this->raw(),true);
        $d['Clear'] = ['Hash'=>str_repeat('A',64),'Angle'=>-1.5,'Releases'=>[],'Score'=>100,'NearbyClears'=>9,'Roles'=>[]];
        $raw = " \n".json_encode($d,JSON_PRETTY_PRINT)."\n ";
        $this->actingAs(User::factory()->create())->post('/puzzles', ['puzzle_file'=>UploadedFile::fake()->createWithContent('my-puzzle.json',$raw),'difficulty'=>'gentle'])->assertSessionHasNoErrors()->assertRedirect();
        $p = Puzzle::firstOrFail();
        $this->assertSame($raw,$p->document);
        $this->assertTrue($p->has_replay);
        $this->get(route('puzzles.show',$p))->assertOk()->assertSee('game to verify');
    }
    public function test_pasted_json_is_not_trimmed_and_duplicates_are_reused(): void {
        $raw = " \n".$this->raw()." \n";
        $this->publish(['puzzle_json'=>$raw]);
        $this->assertSame($raw, Puzzle::first()->document);
        $this->post('/puzzles',['puzzle_json'=>$raw,'difficulty'=>'expert'])->assertRedirect();
        $this->assertDatabaseCount('puzzles',1);
    }
    public function test_invalid_documents_and_ambiguous_input_are_rejected(): void {
        $this->actingAs(User::factory()->create());
        foreach (['', '{}', '[]', '{broken', str_repeat('x',100001)] as $raw) {
            $this->post('/puzzles',['puzzle_json'=>$raw,'difficulty'=>'gentle'])->assertSessionHasErrors('puzzle_json');
        }
        $d=json_decode($this->raw(),true);$d['Pegs'][0]['Home']['X']='" onload="alert(1)';
        $this->post('/puzzles',['puzzle_json'=>json_encode($d),'difficulty'=>'gentle'])->assertSessionHasErrors('puzzle_json');
        $d=json_decode($this->raw(),true);$d['RequiredFields']=[99];
        $this->post('/puzzles',['puzzle_json'=>json_encode($d),'difficulty'=>'gentle'])->assertSessionHasErrors('puzzle_json');
        $this->post('/puzzles',['puzzle_file'=>UploadedFile::fake()->createWithContent('p.json',$this->raw()),'puzzle_json'=>$this->raw(),'difficulty'=>'gentle'])->assertSessionHasErrors('puzzle_json');
        $this->assertDatabaseCount('puzzles',0);
    }
    public function test_search_mechanic_difficulty_sort_and_personal_collection(): void {
        $p=$this->publish();
        $this->get('/?q=Borrow&mechanic=Gravity&difficulty=tricky&sort=popular')->assertOk()->assertSee('Borrow the bend');
        $this->get('/?q=Absent')->assertOk()->assertDontSee('Borrow the bend');
        $this->get('/?difficulty=expert')->assertOk()->assertDontSee('Borrow the bend');
        $this->get('/?mine=1')->assertOk()->assertSee('Borrow the bend');
        $this->actingAs(User::factory()->create())->get('/?mine=1')->assertOk()->assertDontSee('Borrow the bend');
    }
    public function test_only_owner_can_remove_puzzle(): void {
        $p=$this->publish();$owner=auth()->user();
        $this->actingAs(User::factory()->create())->delete(route('puzzles.destroy',$p))->assertForbidden();
        $this->assertDatabaseCount('puzzles',1);
        $this->actingAs($owner)->delete(route('puzzles.destroy',$p))->assertRedirect();
        $this->get(route('puzzles.show',$p))->assertNotFound();
    }
    public function test_creator_text_is_escaped(): void {
        $d=json_decode($this->raw(),true);$d['Title']='<script>alert(1)</script>';
        $p=$this->publish(['puzzle_json'=>json_encode($d),'notes'=>'<img src=x onerror=alert(1)>']);
        $this->get(route('puzzles.show',$p))->assertOk()->assertDontSee('<script>alert(1)</script>',false)->assertSee('&lt;script&gt;',false)->assertSee('&lt;img',false);
    }
    public function test_registration_login_and_logout(): void {
        $this->post('/register',['name'=>'New gardener','email'=>'GARDENER@example.com','password'=>'a-long-test-password','password_confirmation'=>'a-long-test-password'])->assertRedirect('/puzzles/share');
        $this->assertAuthenticated();
        $this->post('/logout')->assertRedirect('/');$this->assertGuest();
        $this->post('/login',['email'=>'gardener@example.com','password'=>'incorrect'])->assertSessionHasErrors('email');
        $this->post('/login',['email'=>'gardener@example.com','password'=>'a-long-test-password'])->assertRedirect('/');$this->assertAuthenticated();
    }
}

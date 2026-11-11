<?php
namespace Tests\Feature;
use App\Models\Puzzle;
use App\Models\User;
use App\Services\PublishPuzzle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;
class PuzzleApiTest extends TestCase
{
    use RefreshDatabase;
    private function raw(): string { return file_get_contents(base_path('tests/Fixtures/pull.json')); }
    private function token(?User $user = null, array $abilities = ['puzzles:write']): string
    {
        return ($user ?? User::factory()->create())->createToken('Test game', $abilities, now()->addDays(90))->plainTextToken;
    }
    private function headers(string $token): array { return ['Authorization' => 'Bearer '.$token]; }
    private function puzzle(?User $owner = null, ?string $title = null, string $difficulty = 'tricky'): Puzzle
    {
        $d=json_decode($this->raw(),true);
        if ($title) $d['Title']=$title;
        return app(PublishPuzzle::class)->publish($owner ?? User::factory()->create(), json_encode($d), $difficulty, null);
    }
    public function test_game_can_sign_in_and_get_hashed_expiring_token_without_session(): void
    {
        $user=User::factory()->create(['email'=>'gardener@example.com', 'password'=>'game-password']);
        $r=$this->postJson('/api/v1/tokens', ['email'=>'GARDENER@example.com','password'=>'game-password','name'=>'Desktop']);
        $r->assertCreated()->assertHeader('Cache-Control','no-store, private')->assertJsonPath('token_type','Bearer');
        $plain=$r->json('token');
        $stored=$user->tokens()->firstOrFail();
        $this->assertNotSame($plain, $stored->token);
        $this->assertSame(64, strlen($stored->token));
        $this->assertTrue($stored->expires_at->isFuture());
        $this->getJson('/api/v1/me',$this->headers($plain))->assertOk()->assertJsonPath('data.name',$user->name)->assertJsonMissingPath('data.email');
    }
    public function test_login_errors_do_not_issue_tokens_and_login_is_throttled(): void
    {
        $user=User::factory()->create(['password'=>'correct-password']);
        $this->postJson('/api/v1/tokens',['email'=>$user->email,'password'=>'bad','name'=>'Game'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/tokens',['email'=>'nobody@example.com','password'=>'bad','name'=>'Game'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/tokens',['email'=>[],'password'=>'bad','name'=>'Game'])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens',0);
        $this->postJson('/api/v1/tokens',[])->assertUnprocessable();
        $this->postJson('/api/v1/tokens',[])->assertUnprocessable();
        $this->postJson('/api/v1/tokens',[])->assertStatus(429)->assertHeader('Retry-After');
    }
    public function test_write_requires_valid_unexpired_token_and_write_ability(): void
    {
        $body=['puzzle_json'=>$this->raw(),'difficulty'=>'tricky'];
        $this->postJson('/api/v1/puzzles',$body)->assertUnauthorized();
        $this->postJson('/api/v1/puzzles',$body,$this->headers('invalid'))->assertUnauthorized();
        $user=User::factory()->create();
        $expired=$user->createToken('Expired',['puzzles:write'],now()->subMinute())->plainTextToken;
        $this->postJson('/api/v1/puzzles',$body,$this->headers($expired))->assertUnauthorized();
        $this->postJson('/api/v1/puzzles',$body,$this->headers($this->token($user,[])))->assertForbidden();
        $this->assertDatabaseCount('puzzles',0);
    }
    public function test_cookie_login_does_not_substitute_for_api_bearer_auth(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/api/v1/puzzles',['puzzle_json'=>$this->raw(),'difficulty'=>'tricky'])->assertUnauthorized();
    }
    public function test_upload_returns_share_url_and_preserves_export_on_retry_and_download(): void
    {
        $raw=" \n".$this->raw()."\n ";
        $user=User::factory()->create();$headers=$this->headers($this->token($user));
        $body=['puzzle_json'=>$raw,'difficulty'=>'tricky','notes'=>'Try the left side.', 'user_id'=>999];
        $r=$this->postJson('/api/v1/puzzles',$body,$headers)->assertCreated()->assertJsonPath('created',true);
        $id=$r->json('data.id');
        $r->assertJsonPath('data.share_url',url('/puzzles/'.$id))->assertJsonPath('data.sha256',hash('sha256',$raw))->assertJsonMissingPath('data.document')->assertJsonMissingPath('data.user_id');
        $p=Puzzle::firstOrFail();$this->assertSame($user->id,$p->user_id);$this->assertSame($raw,$p->document);
        $this->postJson('/api/v1/puzzles',array_merge($body,['difficulty'=>'expert']),$headers)->assertOk()->assertJsonPath('created',false)->assertJsonPath('data.id',$id)->assertJsonPath('data.difficulty','tricky');
        $this->assertDatabaseCount('puzzles',1);
        $this->getJson('/api/v1/puzzles/'.$id)->assertOk()->assertJsonPath('data.puzzle_json',$raw);
        $this->get('/api/v1/puzzles/'.$id.'/download')->assertOk()->assertContent($raw)->assertHeader('Content-Type','application/json; charset=UTF-8');
        $this->assertSame(1,$p->fresh()->downloads);
    }
    public function test_multipart_upload_and_replay_preservation(): void
    {
        $d=json_decode($this->raw(),true);
        $d['Clear']=['Hash'=>str_repeat('A',64),'Angle'=>-1.5,'Releases'=>[],'Score'=>100,'NearbyClears'=>9,'Roles'=>[]];
        $raw=json_encode($d,JSON_PRETTY_PRINT);
        $this->post('/api/v1/puzzles',['puzzle_file'=>UploadedFile::fake()->createWithContent('puzzle.json',$raw),'difficulty'=>'gentle'], $this->headers($this->token()))->assertCreated()->assertJsonPath('data.has_replay',true);
        $this->assertSame($raw,Puzzle::firstOrFail()->document);
    }
    public function test_malformed_or_ambiguous_upload_is_json_422(): void
    {
        $headers=$this->headers($this->token());
        foreach (['{}','[]','{broken',str_repeat('x',100001)] as $raw) {
            $this->postJson('/api/v1/puzzles',['puzzle_json'=>$raw,'difficulty'=>'gentle'],$headers)->assertUnprocessable()->assertJsonValidationErrors('puzzle_json');
        }
        $this->postJson('/api/v1/puzzles',['puzzle_json'=>$this->raw(),'difficulty'=>'impossible'],$headers)->assertUnprocessable()->assertJsonValidationErrors('difficulty');
        $this->post('/api/v1/puzzles',['puzzle_file'=>UploadedFile::fake()->createWithContent('p.json',$this->raw()),'puzzle_json'=>$this->raw(),'difficulty'=>'gentle'],$headers)->assertUnprocessable()->assertJsonValidationErrors('puzzle_json');
        $this->get('/api/v1/puzzles/not-found')->assertNotFound()->assertHeader('Content-Type','application/json');
        $this->assertDatabaseCount('puzzles',0);
    }
    public function test_public_catalog_filters_paginates_and_keeps_exports_and_emails_out_of_list(): void
    {
        $p=$this->puzzle(title:'Bend to the left');$other=$this->puzzle(title:'A gentle garden',difficulty:'gentle');
        $this->getJson('/api/v1/puzzles?per_page=1&sort=oldest')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.id',$p->slug)->assertJsonPath('meta.total',2)->assertJsonPath('meta.last_page',2)->assertJsonMissingPath('data.0.puzzle_json')->assertJsonMissingPath('data.0.creator.email');
        $this->getJson('/api/v1/puzzles?q=Bend&mechanic=Gravity&difficulty=tricky')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.id',$p->slug);
        $other->update(['downloads'=>10]);
        $this->getJson('/api/v1/puzzles?sort=popular')->assertOk()->assertJsonPath('data.0.id',$other->slug);
        $this->getJson('/api/v1/puzzles?per_page=51')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/v1/puzzles?mechanic=unknown')->assertUnprocessable();
    }
    public function test_personal_catalog_and_delete_enforce_owner(): void
    {
        $owner=User::factory()->create();$mine=$this->puzzle($owner);$others=$this->puzzle();
        $headers=$this->headers($this->token($owner));
        $this->getJson('/api/v1/me/puzzles',$headers)->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.id',$mine->slug);
        $this->deleteJson('/api/v1/puzzles/'.$others->slug,[],$headers)->assertForbidden();
        $this->deleteJson('/api/v1/puzzles/'.$mine->slug,[],$headers)->assertNoContent();
        $this->getJson('/api/v1/puzzles/'.$mine->slug)->assertNotFound();
        $this->assertDatabaseHas('puzzles',['id'=>$others->id]);
    }
    public function test_current_token_can_be_revoked_and_no_longer_authenticates(): void
    {
        $headers=$this->headers($this->token());
        $this->deleteJson('/api/v1/tokens/current',[],$headers)->assertNoContent();
        Auth::forgetGuards();
        $this->getJson('/api/v1/me',$headers)->assertUnauthorized();
    }
    public function test_web_tokens_are_shown_once_hashed_and_only_revoked_by_owner(): void
    {
        $owner=User::factory()->create();
        $this->get('/account/tokens')->assertRedirect('/login');
        $r=$this->actingAs($owner)->post('/account/tokens',['name'=>'My laptop'])->assertOk()->assertSee('Your new token is ready.');
        $plain=$r->viewData('newToken');$token=PersonalAccessToken::findToken($plain);$this->assertNotNull($token);
        $this->get('/account/tokens')->assertOk()->assertDontSee($plain);
        $this->actingAs(User::factory()->create())->delete('/account/tokens/'.$token->id)->assertNotFound();
        $this->actingAs($owner)->delete('/account/tokens/'.$token->id)->assertRedirect('/account/tokens');
        $this->assertNull(PersonalAccessToken::findToken($plain));
    }
    public function test_active_token_limit_and_upload_limit(): void
    {
        $user=User::factory()->create();
        for ($i=0;$i<10;$i++) $this->token($user);
        $this->actingAs($user)->post('/account/tokens',['name'=>'Too many'])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('personal_access_tokens',10);
        Auth::forgetGuards();
        $headers=$this->headers($this->token());
        for ($i=0;$i<10;$i++) $this->postJson('/api/v1/puzzles',['puzzle_json'=>$this->raw(),'difficulty'=>'gentle'],$headers)->assertSuccessful();
        $this->postJson('/api/v1/puzzles',['puzzle_json'=>$this->raw(),'difficulty'=>'gentle'],$headers)->assertStatus(429);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_checks_the_application_database(): void
    {
        $this->getJson('/up')->assertOk()->assertJsonPath('status', 'up');
    }

    public function test_health_fails_without_application_tables_and_does_not_expose_database_details(): void
    {
        config(['app.debug' => false]);
        DB::statement('DROP TABLE personal_access_tokens');
        $this->getJson('/up')->assertStatus(500)->assertExactJson(['status' => 'down']);
        $this->get('/up')->assertStatus(500)->assertDontSee('personal_access_tokens')->assertDontSee('SQLSTATE');
    }

    public function test_cleanup_preserves_active_and_recently_expired_tokens(): void
    {
        $user = User::factory()->create();
        $old = $user->createToken('old', ['puzzles:write'], now()->subDays(2))->accessToken;
        $recent = $user->createToken('recent', ['puzzles:write'], now()->subHour())->accessToken;
        $active = $user->createToken('active', ['puzzles:write'], now()->addDays(2))->accessToken;
        $this->artisan('sanctum:prune-expired', ['--hours' => 24])->assertSuccessful();
        $this->assertNull(PersonalAccessToken::find($old->id));
        $this->assertNotNull(PersonalAccessToken::find($recent->id));
        $this->assertNotNull(PersonalAccessToken::find($active->id));
    }
}

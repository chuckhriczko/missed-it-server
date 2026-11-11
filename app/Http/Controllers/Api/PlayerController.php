<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GameTokens;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
// Name-only players for sharing straight from the game. A player is a user with a placeholder
// email and a random password, so it can never sign in on the website; the game keeps its token.
class PlayerController extends Controller
{
    public const EMAIL_DOMAIN = 'players.missedit.invalid';
    private static function nameRules(): array { return ['name' => ['required', 'string', 'max:40', 'regex:/\S/']]; }
    public function store(Request $request, GameTokens $tokens)
    {
        $data = $request->validate(self::nameRules());
        $user = User::create(['name' => trim($data['name']), 'email' => 'player-'.Str::uuid().'@'.self::EMAIL_DOMAIN, 'password' => Str::random(64)]);
        $token = $tokens->issue($user, 'Puzzle Studio');
        return response()->json(['token_type' => 'Bearer', 'token' => $token->plainTextToken, 'expires_at' => $token->accessToken->expires_at->toIso8601String(), 'name' => $user->name], 201)
            ->header('Cache-Control', 'no-store');
    }
    public function rename(Request $request)
    {
        $data = $request->validate(self::nameRules());
        $request->user()->forceFill(['name' => trim($data['name'])])->save();
        return response()->json(['data' => ['name' => $request->user()->name]])->header('Cache-Control', 'no-store');
    }
}

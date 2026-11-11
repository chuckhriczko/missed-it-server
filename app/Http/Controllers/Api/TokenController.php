<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GameTokens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
class TokenController extends Controller
{
    public function store(Request $request, GameTokens $tokens)
    {
        $data = $request->validate(['email' => ['required','email','max:254'], 'password' => ['required','string','max:4096'], 'name' => ['required','string','max:60']]);
        $user = User::where('email', strtolower($data['email']))->first();
        // Check a fixed dummy hash too, so unknown accounts still pay the hash cost.
        $valid = Hash::check($data['password'], $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (!$user || !$valid) throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        $token = $tokens->issue($user, $data['name']);
        return response()->json(['token_type' => 'Bearer', 'token' => $token->plainTextToken, 'expires_at' => $token->accessToken->expires_at->toIso8601String()], 201)
            ->header('Cache-Control', 'no-store');
    }
    public function me(Request $request)
    {
        return response()->json(['data' => ['name' => $request->user()->name]])->header('Cache-Control', 'no-store');
    }
    public function destroy(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->noContent();
    }
}

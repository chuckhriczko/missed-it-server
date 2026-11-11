<?php
namespace App\Http\Controllers;
use App\Services\GameTokens;
use Illuminate\Http\Request;
class ApiTokenController extends Controller
{
    public function index(Request $request)
    {
        return response()->view('tokens.index', ['tokens' => $request->user()->tokens()->latest()->get()])->header('Cache-Control', 'no-store');
    }
    public function store(Request $request, GameTokens $tokens)
    {
        $data = $request->validate(['name' => ['required','string','max:60']]);
        $token = $tokens->issue($request->user(), $data['name']);
        // Render once instead of flashing the plaintext token into session storage.
        return response()->view('tokens.index', ['tokens' => $request->user()->tokens()->latest()->get(), 'newToken' => $token->plainTextToken])
            ->header('Cache-Control', 'no-store');
    }
    public function destroy(Request $request, string $token)
    {
        $request->user()->tokens()->whereKey($token)->firstOrFail()->delete();
        return redirect()->route('tokens.index')->with('status', 'API token revoked.');
    }
}

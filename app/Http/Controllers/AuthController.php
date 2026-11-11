<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller {
    public function register(Request $request) {
        if (is_string($request->input('email'))) $request->merge(['email' => strtolower($request->input('email'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:254', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);
        Auth::login(User::create($data));
        $request->session()->regenerate();
        return redirect()->intended(route('puzzles.create'));
    }
    public function login(Request $request) {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $data['email'] = strtolower($data['email']);
        if (! Auth::attempt($data, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }
        $request->session()->regenerate();
        return redirect()->intended(route('puzzles.index'));
    }
    public function logout(Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('puzzles.index');
    }
}

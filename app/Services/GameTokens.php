<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
class GameTokens
{
    public function issue(User $user, string $name): NewAccessToken
    {
        if ($user->tokens()->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count() >= 10) {
            throw ValidationException::withMessages(['name' => 'You already have 10 active tokens. Revoke one before creating another.']);
        }
        return $user->createToken($name, ['puzzles:write'], now()->addDays(90));
    }
}

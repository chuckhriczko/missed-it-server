<?php
namespace App\Services;
use App\Models\Puzzle;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
class PuzzleCatalog
{
    public const MECHANICS = ['Gravity', 'Repulsor', 'Accelerator', 'Orbiter', 'Capture', 'Bumper', 'Portals', 'Surfaces', 'Rivers', 'Blossoms', 'Switches'];
    public static function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'difficulty' => ['nullable', Rule::in(['gentle', 'tricky', 'expert'])],
            'mechanic' => ['nullable', Rule::in(self::MECHANICS)],
            'sort' => ['nullable', Rule::in(['newest', 'popular', 'oldest', 'cleared'])],
        ];
    }
    public function query(array $filters, ?User $owner = null): Builder
    {
        $query = Puzzle::with('user');
        if ($owner) $query->where('user_id', $owner->id);
        if ($q = $filters['q'] ?? null) {
            $query->where(fn ($query) => $query->where('title', 'like', "%{$q}%")
                ->orWhere('brief', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$q}%")));
        }
        if ($filters['difficulty'] ?? null) $query->where('difficulty', $filters['difficulty']);
        if ($filters['mechanic'] ?? null) $query->whereJsonContains('mechanics', $filters['mechanic']);
        match ($filters['sort'] ?? 'newest') {
            'popular' => $query->orderByDesc('downloads')->latest('id'),
            'cleared' => $query->orderByDesc('clears')->latest('id'),
            'oldest' => $query->oldest('id'),
            default => $query->latest('id'),
        };
        return $query;
    }
}

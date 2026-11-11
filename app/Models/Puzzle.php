<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Puzzle extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['mechanics' => 'array', 'has_replay' => 'boolean']; }
    public function getRouteKeyName(): string { return 'slug'; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    /** Search, difficulty, mechanic and sort, shared by the website and the game's API. */
    public function scopeBrowse(Builder $query, array $filters): Builder {
        if ($q = $filters['q'] ?? null) {
            $query->where(fn ($query) => $query->where('title', 'like', "%{$q}%")
                ->orWhere('brief', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$q}%")));
        }
        if ($filters['difficulty'] ?? null) $query->where('difficulty', $filters['difficulty']);
        if ($filters['mechanic'] ?? null) $query->whereJsonContains('mechanics', $filters['mechanic']);
        match ($filters['sort'] ?? 'newest') {
            'popular' => $query->orderByDesc('downloads')->latest('id'),
            'oldest' => $query->oldest('id'),
            default => $query->latest('id'),
        };
        return $query;
    }
    public function board(): array { return json_decode($this->document, true); }
}

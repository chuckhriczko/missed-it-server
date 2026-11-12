<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Puzzle extends Model {
    protected $guarded = ['id'];
    protected function casts(): array { return ['mechanics' => 'array', 'has_replay' => 'boolean']; }
    public function getRouteKeyName(): string { return 'slug'; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function board(): array { return json_decode($this->document, true); }
    protected static function booted(): void
    {
        static::deleted(fn (Puzzle $p) => \Illuminate\Support\Facades\Log::info('Puzzle removed', ['id' => $p->slug, 'title' => $p->title]));
    }
}

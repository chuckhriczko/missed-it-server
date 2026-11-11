<?php
namespace App\Services;
use App\Models\Puzzle;
use App\Models\User;
use Illuminate\Support\Str;
class PublishPuzzle
{
    public function __construct(private PuzzleDocument $parser) {}
    public function publish(User $user, string $raw, string $difficulty, ?string $notes): Puzzle
    {
        $board = $this->parser->parse($raw);
        // The unique index plus firstOrCreate also handles simultaneous retries.
        return Puzzle::firstOrCreate([
            'user_id' => $user->id, 'checksum' => hash('sha256', $raw),
        ], [
            'slug' => (string) Str::uuid(), 'title' => $board['Title'], 'brief' => $board['Brief'],
            'notes' => $notes, 'difficulty' => $difficulty, 'document' => $raw,
            'mechanics' => $this->parser->mechanics($board), 'has_replay' => isset($board['Clear']),
        ]);
    }
}

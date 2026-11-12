<?php
namespace App\Services;
use App\Models\Puzzle;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class PublishPuzzle
{
    public function __construct(private PuzzleDocument $parser) {}
    public function publish(User $user, string $raw, string $difficulty, ?string $notes): Puzzle
    {
        try { $board = $this->parser->parse($raw); }
        catch (ValidationException $e) {
            Log::notice('Puzzle refused', ['creator' => $user->name, 'reason' => collect($e->errors())->flatten()->first()]);
            throw $e;
        }
        // The unique index plus firstOrCreate also handles simultaneous retries.
        $puzzle = Puzzle::firstOrCreate([
            'user_id' => $user->id, 'checksum' => hash('sha256', $raw),
        ], [
            'slug' => (string) Str::uuid(), 'title' => $board['Title'], 'brief' => $board['Brief'],
            'notes' => $notes, 'difficulty' => $difficulty, 'document' => $raw,
            'mechanics' => $this->parser->mechanics($board), 'has_replay' => isset($board['Clear']),
        ]);
        if ($puzzle->wasRecentlyCreated) Log::info('Puzzle shared', ['id' => $puzzle->slug, 'title' => $puzzle->title, 'creator' => $user->name, 'difficulty' => $difficulty, 'has_replay' => $puzzle->has_replay]);
        return $puzzle;
    }
}

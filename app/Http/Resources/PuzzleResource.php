<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class PuzzleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug, 'title' => $this->title, 'brief' => $this->brief,
            'notes' => $this->notes, 'difficulty' => $this->difficulty,
            'mechanics' => $this->mechanics, 'has_replay' => $this->has_replay,
            'downloads' => $this->downloads, 'sha256' => $this->checksum,
            'creator' => ['name' => $this->user->name],
            'created_at' => $this->created_at->toIso8601String(),
            'share_url' => route('puzzles.show', $this->resource),
            'api_url' => route('api.puzzles.show', $this->resource),
            'download_url' => route('api.puzzles.download', $this->resource),
            'puzzle_json' => $this->when($request->routeIs('api.puzzles.show'), fn () => $this->document),
        ];
    }
}

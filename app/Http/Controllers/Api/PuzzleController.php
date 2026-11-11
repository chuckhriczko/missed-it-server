<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Requests\PuzzleUploadRequest;
use App\Http\Resources\PuzzleResource;
use App\Models\Puzzle;
use App\Services\PublishPuzzle;
use App\Services\PuzzleCatalog;
use Illuminate\Http\Request;
class PuzzleController extends Controller
{
    public function index(Request $request, PuzzleCatalog $catalog)
    {
        $filters = $request->validate($catalog::rules() + ['per_page' => ['nullable', 'integer', 'between:1,50'], 'page' => ['nullable', 'integer', 'min:1']]);
        $owner = $request->routeIs('api.me.puzzles') ? $request->user() : null;
        $query = $catalog->query($filters, $owner)->select(['puzzles.id', 'slug', 'user_id', 'title', 'brief', 'notes', 'difficulty', 'mechanics', 'has_replay', 'downloads', 'checksum', 'created_at']);
        return PuzzleResource::collection($query->paginate($filters['per_page'] ?? 24)->withQueryString());
    }
    public function store(PuzzleUploadRequest $request, PublishPuzzle $publisher)
    {
        $puzzle = $publisher->publish($request->user(), $request->documentText(), $request->validated('difficulty'), $request->validated('notes'));
        return (new PuzzleResource($puzzle->load('user')))->additional(['created' => $puzzle->wasRecentlyCreated])
            ->response()->setStatusCode($puzzle->wasRecentlyCreated ? 201 : 200)
            ->header('Location', route('api.puzzles.show', $puzzle));
    }
    public function show(Puzzle $puzzle) { return new PuzzleResource($puzzle->load('user')); }
    public function destroy(Request $request, Puzzle $puzzle)
    {
        abort_unless($request->user()->id === $puzzle->user_id, 403);
        $puzzle->delete();
        return response()->noContent();
    }
}

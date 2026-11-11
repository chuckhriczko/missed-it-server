<?php
namespace App\Http\Controllers;
use App\Models\Puzzle;
use App\Http\Requests\PuzzleUploadRequest;
use App\Services\PuzzleCatalog;
use App\Services\PublishPuzzle;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class PuzzleController extends Controller {
    public function index(Request $request, PuzzleCatalog $catalog) {
        $filters = $request->validate($catalog::rules() + ['mine' => ['nullable', 'in:1']]);
        if (($filters['mine'] ?? null) && !$request->user()) return redirect()->guest(route('login'));
        $query = $catalog->query($filters, ($filters['mine'] ?? null) ? $request->user() : null);
        return view('puzzles.index', ['puzzles' => $query->paginate(12)->withQueryString(), 'total' => Puzzle::count(), 'mechanics' => $catalog::MECHANICS]);
    }
    public function create() { return view('puzzles.create'); }
    public function store(PuzzleUploadRequest $request, PublishPuzzle $publisher) {
        $puzzle = $publisher->publish($request->user(), $request->documentText(), $request->validated('difficulty'), $request->validated('notes'));
        return redirect()->route('puzzles.show', $puzzle)->with('status', $puzzle->wasRecentlyCreated
            ? 'Your garden is shared. Copy its link to invite someone in.' : 'You have already shared this puzzle.');
    }
    public function show(Puzzle $puzzle) { return view('puzzles.show', ['puzzle' => $puzzle->load('user')]); }
    public function download(Puzzle $puzzle) {
        $puzzle->increment('downloads');
        return response($puzzle->document, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.(Str::slug($puzzle->title) ?: 'puzzle').'.json"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
    public function destroy(Request $request, Puzzle $puzzle) {
        abort_unless($request->user()->id === $puzzle->user_id, 403);
        $puzzle->delete();
        return redirect()->route('puzzles.index', ['mine' => 1])->with('status', 'Puzzle removed.');
    }
}

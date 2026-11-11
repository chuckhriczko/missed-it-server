<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PuzzleController;
use App\Models\Puzzle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
/** What the game's Puzzle Garden browser reads: a page of puzzle summaries, then one puzzle's exact JSON. */
class GardenController extends Controller {
    public const PER_PAGE = 20;
    public function index(Request $request) {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'difficulty' => ['nullable', Rule::in(['gentle', 'tricky', 'expert'])],
            'mechanic' => ['nullable', Rule::in(PuzzleController::MECHANICS)],
            'sort' => ['nullable', Rule::in(['newest', 'popular', 'oldest'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);
        $page = Puzzle::with('user')->browse($filters)->paginate(self::PER_PAGE);
        return response()->json([
            'puzzles' => $page->getCollection()->map(fn (Puzzle $p) => [
                'id' => $p->slug,
                'title' => $p->title,
                'brief' => $p->brief,
                'creator' => $p->user->name,
                'difficulty' => $p->difficulty,
                'mechanics' => $p->mechanics,
                'downloads' => $p->downloads,
                'hasReplay' => $p->has_replay,
                'shared' => $p->created_at->toDateString(),
            ])->values(),
            'page' => $page->currentPage(),
            'pages' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }
    /** The submitted text byte for byte, so the game can replay its recorded clear. Counts as a download. */
    public function show(Puzzle $puzzle) {
        $puzzle->increment('downloads');
        return response($puzzle->document, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

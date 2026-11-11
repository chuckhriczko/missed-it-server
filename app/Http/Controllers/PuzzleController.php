<?php
namespace App\Http\Controllers;
use App\Models\Puzzle;
use App\Services\PuzzleDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class PuzzleController extends Controller {
    public const MECHANICS = ['Gravity', 'Repulsor', 'Accelerator', 'Orbiter', 'Capture', 'Bumper', 'Portals', 'Surfaces', 'Rivers', 'Blossoms'];
    public function index(Request $request) {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'difficulty' => ['nullable', Rule::in(['gentle', 'tricky', 'expert'])],
            'mechanic' => ['nullable', Rule::in(self::MECHANICS)],
            'sort' => ['nullable', Rule::in(['newest', 'popular', 'oldest'])],
            'mine' => ['nullable', 'in:1'],
        ]);
        $query = Puzzle::with('user')->browse($filters);
        if ($filters['mine'] ?? null) {
            if (! $request->user()) return redirect()->guest(route('login'));
            $query->where('user_id', $request->user()->id);
        }
        return view('puzzles.index', ['puzzles' => $query->paginate(12)->withQueryString(), 'total' => Puzzle::count(), 'mechanics' => self::MECHANICS]);
    }
    public function create() { return view('puzzles.create'); }
    public function store(Request $request, PuzzleDocument $parser) {
        $data = $request->validate([
            'puzzle_file' => ['nullable', 'file', 'max:100'],
            'puzzle_json' => ['nullable', 'string', 'max:100000'],
            'difficulty' => ['required', Rule::in(['gentle', 'tricky', 'expert'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($request->hasFile('puzzle_file') && filled($data['puzzle_json'] ?? null)) {
            throw ValidationException::withMessages(['puzzle_json' => 'Choose a file or paste JSON, rather than supplying both.']);
        }
        $raw = $request->hasFile('puzzle_file') ? $request->file('puzzle_file')->get() : ($data['puzzle_json'] ?? '');
        $board = $parser->parse($raw);
        $checksum = hash('sha256', $raw);
        $existing = Puzzle::where('user_id', $request->user()->id)->where('checksum', $checksum)->first();
        if ($existing) return redirect()->route('puzzles.show', $existing)->with('status', 'You have already shared this puzzle.');
        $puzzle = Puzzle::create([
            'slug' => (string) Str::uuid(), 'user_id' => $request->user()->id,
            'title' => $board['Title'], 'brief' => $board['Brief'], 'notes' => $data['notes'] ?? null,
            'difficulty' => $data['difficulty'], 'document' => $raw, 'checksum' => $checksum,
            'mechanics' => $parser->mechanics($board), 'has_replay' => isset($board['Clear']),
        ]);
        return redirect()->route('puzzles.show', $puzzle)->with('status', 'Your garden is shared. Copy its link to invite someone in.');
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

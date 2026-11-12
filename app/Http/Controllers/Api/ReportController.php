<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\Logalyzer;
use Illuminate\Http\Request;
use function Illuminate\Support\defer;
// Error reports from the game, forwarded to Logalyzer so the game never holds a Logalyzer token.
// Only listed fields are kept: the game sends no account, device ID or address, and none is added here.
class ReportController extends Controller
{
    private const CONTEXT = ['version' => 20, 'platform' => 30, 'os_version' => 60, 'device' => 80, 'engine' => 30, 'screen' => 20, 'scene' => 60];
    public function store(Request $request)
    {
        $rules = [
            'environment' => ['required', 'in:production,development'],
            'context' => ['nullable', 'array'],
            'entries' => ['required', 'array', 'min:1', 'max:20'],
            'entries.*.severity' => ['required', 'in:warning,error,critical'],
            'entries.*.message' => ['required', 'string', 'max:2000'],
            'entries.*.source_file' => ['nullable', 'string', 'max:300'],
            'entries.*.line_number' => ['nullable', 'integer', 'min:1'],
            'entries.*.occurred_at' => ['nullable', 'date'],
            'entries.*.count' => ['nullable', 'integer', 'between:1,10000'],
            'entries.*.trace' => ['nullable', 'string', 'max:4000'],
        ];
        foreach (self::CONTEXT as $key => $max) $rules["context.$key"] = ['nullable', 'string', "max:$max"];
        $data = $request->validate($rules);
        $context = array_filter(array_intersect_key($data['context'] ?? [], self::CONTEXT), fn ($v) => $v !== null && $v !== '');
        $entries = array_map(fn (array $e) => array_filter([
            'severity' => $e['severity'], 'message' => $e['message'], 'section' => 'Game',
            'source_file' => $e['source_file'] ?? null, 'line_number' => $e['line_number'] ?? null,
            'occurred_at' => isset($e['occurred_at']) ? \Illuminate\Support\Carbon::parse($e['occurred_at'])->toIso8601String() : now()->toIso8601String(),
            'context' => $context + array_filter(['count' => $e['count'] ?? null, 'trace' => $e['trace'] ?? null], fn ($v) => $v !== null),
        ], fn ($v) => $v !== null), $data['entries']);
        $forward = Logalyzer::configured();
        if ($forward) defer(fn () => Logalyzer::send(config('services.logalyzer.game_log'), $data['environment'], 'godot', $entries));
        return response()->json(['accepted' => count($entries), 'forwarded' => $forward], 202);
    }
}

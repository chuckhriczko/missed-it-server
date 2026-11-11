<?php
namespace App\Services;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Structural intake checks, not a port of the game's physics or replay verifier. */
class PuzzleDocument {
    public function parse(string $raw): array {
        $fail = fn (string $message) => throw ValidationException::withMessages(['puzzle_json' => $message]);
        if ($raw === '' || strlen($raw) > 100000) $fail('Paste a Puzzle Studio export or choose a JSON file of at most 100 KB.');
        try { $object = json_decode($raw, false, 24, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { $fail('This is not valid puzzle JSON: '.$e->getMessage()); }
        if (! $object instanceof \stdClass) $fail('A puzzle must be a JSON object. Export it from Puzzle Studio → Library → Share JSON.');
        $d = json_decode($raw, true);
        $allowed = explode(' ', 'BuiltIn UniqueGoal Version Id Title Brief StarScore SuggestedSection SuggestedLevel Pegs Home Portals RequiredFields RequiredGrazes FinishGrazes RequiredGates RequiredBumpers ArenaHeight LaunchOrigin Boss Gift Banks BankAfterStep Clear Surfaces LinkedTargets Rivers RequiredRivers ThreeStarScore ClockStart');
        if (array_diff(array_keys($d), $allowed)) $fail('Unrecognized puzzle properties. Use a version 1 Puzzle Studio export, rather than a campaign or Room Workshop file.');
        $vector = ['required', 'array:X,Y'];
        $number = ['required', 'numeric', 'between:-10000,10000'];
        $rules = [
            'Version' => ['required', 'integer', 'in:1'],
            'Id' => ['required', 'string', 'regex:/^[a-fA-F0-9]{32}$/D'],
            'Title' => ['required', 'string', 'max:56'],
            'Brief' => ['required', 'string', 'max:220'],
            'StarScore' => ['required', 'integer', 'between:50,10000'],
            'Banks' => ['required', 'integer', 'between:0,3'],
            'BankAfterStep' => ['nullable', 'integer', 'min:0'],
            'UniqueGoal' => ['nullable', 'integer', 'between:0,32'],
            'ArenaHeight' => ['nullable', 'numeric', 'between:700,1400'],
            'ClockStart' => ['nullable', 'numeric', 'between:0,86400'],
            'ThreeStarScore' => ['nullable', 'integer', 'min:'.(is_int($d['StarScore'] ?? null) ? $d['StarScore'] : 50), 'max:20000'],
            'SuggestedSection' => ['nullable', 'string', 'max:80'],
            'SuggestedLevel' => ['nullable', 'integer', 'between:1,10'],
            'Home' => ['required', 'array:Position,Radius'],
            'Home.Position' => $vector,
            'Home.Position.X' => ['required', 'numeric', 'between:28,612'],
            'Home.Position.Y' => ['required', 'numeric', 'between:31,1400'],
            'Home.Radius' => ['required', 'numeric', 'between:18,100'],
            'Pegs' => ['present', 'array', 'list', 'max:32'],
            'Pegs.*' => ['required', 'array:Id,Home,Position,Previous,Kind,Radius,Field,Strength,OrbitRadius,OrbitSpeed,Phase,Drift,Breath,Flip,Draft'],
            'Pegs.*.Id' => ['required', 'integer', 'min:0', 'distinct:strict'],
            'Pegs.*.Home' => $vector,
            'Pegs.*.Home.X' => ['required', 'numeric', 'between:28,612'],
            'Pegs.*.Home.Y' => ['required', 'numeric', 'between:31,1400'],
            'Pegs.*.Kind' => ['required', Rule::in(['Neutral','Gravity','Repulsor','Accelerator','Orbiter','Capture','Bumper'])],
            'Pegs.*.Radius' => ['required', 'numeric', 'between:8,100'],
            'Pegs.*.Field' => ['required', 'numeric', 'between:0,500'],
            'Pegs.*.Strength' => ['required', 'numeric', 'between:0,3000'],
            'Pegs.*.OrbitRadius' => ['required', 'numeric', 'between:0,150'],
            'Pegs.*.OrbitSpeed' => ['required', 'numeric', 'gt:0.05', 'max:4'],
            'Pegs.*.Phase' => $number,
            'Pegs.*.Drift' => ['sometimes','numeric','between:0,500'],
            'Pegs.*.Breath' => ['sometimes','numeric','between:0,86400'],
            'Pegs.*.Flip' => ['sometimes','boolean'],
            'Portals' => ['present','array','list','max:3'],
            'Portals.*' => ['required','array:Id,A,B'],
            'Portals.*.Id' => ['required','integer','min:0','distinct:strict'],
            'Surfaces' => ['nullable','array','list','max:32'],
            'Surfaces.*' => ['required','array:Id,A,B,Kind,Thickness,PassingNormal,Current,Swing,Speed'],
            'Surfaces.*.Id' => ['required','integer','min:0','distinct:strict'],
            'Surfaces.*.Kind' => ['required',Rule::in(['Wall','Mirror','OneWay','Breakable','Water','Rotating'])],
            'Surfaces.*.Thickness' => ['sometimes','numeric','between:0,200'],
            'LinkedTargets' => ['nullable','array','list','max:8'],
            'LinkedTargets.*' => ['required','array:Id,Position,Radius'],
            'LinkedTargets.*.Id' => ['required','integer','min:0','distinct:strict'],
            'LinkedTargets.*.Radius' => ['sometimes','numeric','between:1,100'],
            'Rivers' => ['nullable','array','list','max:8'],
            'Rivers.*' => ['required','array:Id,Points,Width,Speed,ExitSpeed,Waterfall'],
            'Rivers.*.Id' => ['required','integer','min:0','distinct:strict'],
            'Rivers.*.Points' => ['required','array','list','min:2','max:64'],
            'Rivers.*.Width' => ['sometimes','numeric','between:1,500'],
            'Rivers.*.Speed' => ['sometimes','numeric','between:0,10000'],
            'Rivers.*.ExitSpeed' => ['sometimes','numeric','between:0,10000'],
            'Rivers.*.Waterfall' => ['sometimes','boolean'],
            'Clear' => ['nullable','array:Hash,Angle,Releases,Score,NearbyClears,Roles,LaunchDelay,Chain'],
            'Clear.Hash' => ['required_with:Clear','string','max:128'],
            'Clear.Angle' => ['required_with:Clear','numeric','between:-10000,10000'],
            'Clear.Releases' => ['sometimes','array','list','max:60'],
            'Clear.Releases.*' => ['required','array:Tick,PegId,AimAngle'],
            'Clear.Releases.*.Tick' => ['required','integer','between:0,43200'],
            'Clear.Releases.*.PegId' => ['required','integer','min:0'],
            'Clear.Releases.*.AimAngle' => ['nullable','numeric','between:-10000,10000'],
            'Clear.Score' => ['required_with:Clear','integer','min:0'],
            'Clear.NearbyClears' => ['required_with:Clear','integer','between:0,9'],
            'Clear.Roles' => ['sometimes','array','list','max:128'],
            'Clear.Roles.*' => ['string','max:1000'],
            'Clear.Chain' => ['nullable','array','list','max:512'],
            'Clear.Chain.*' => ['string','max:1000'],
            'Clear.LaunchDelay' => ['nullable','numeric','between:0,86400'],
            'BuiltIn' => ['nullable','array:Room,Title,Fingerprint'],
            'BuiltIn.Room' => ['required_with:BuiltIn','integer','between:1,60'],
            'BuiltIn.Title' => ['required_with:BuiltIn','string','max:100'],
            'BuiltIn.Fingerprint' => ['required_with:BuiltIn','string','size:64'],
            'Boss' => ['nullable','array'], 'Gift' => ['nullable','array'],
        ];
        foreach (['RequiredFields','RequiredGrazes','FinishGrazes','RequiredGates','RequiredBumpers','RequiredRivers'] as $key) {
            $rules[$key] = in_array($key, ['RequiredBumpers','RequiredRivers']) ? ['nullable','array','list','max:32'] : ['present','array','list','max:32'];
            $rules[$key.'.*'] = ['integer','min:0','distinct:strict'];
        }
        foreach (['LaunchOrigin','Pegs.*.Position','Pegs.*.Previous','Pegs.*.Draft','Surfaces.*.PassingNormal','Surfaces.*.Current'] as $path) {
            $rules[$path] = ['sometimes','nullable','array:X,Y'];
            $rules[$path.'.X'] = ['required_with:'.$path,'numeric','between:-10000,10000'];
            $rules[$path.'.Y'] = ['required_with:'.$path,'numeric','between:-10000,10000'];
        }
        foreach (['Surfaces.*.A','Surfaces.*.B','LinkedTargets.*.Position','Rivers.*.Points.*','Portals.*.A.Center','Portals.*.B.Center'] as $path) {
            $rules[$path] = $vector;
            $rules[$path.'.X'] = $number; $rules[$path.'.Y'] = $number;
        }
        foreach (['A','B'] as $side) {
            $rules['Portals.*.'.$side] = ['required','array:Center,Radius,Facing'];
            $rules['Portals.*.'.$side.'.Radius'] = ['required','numeric','between:1,200'];
            $rules['Portals.*.'.$side.'.Facing'] = $number;
        }
        if (isset($d['Clear'])) {
            foreach (['Hash','Angle','Releases','Score','NearbyClears','Roles'] as $key) if (is_array($d['Clear']) && !array_key_exists($key, $d['Clear'])) $fail("Clear is missing $key.");
        }
        $validator = Validator::make($d, $rules);
        if ($validator->fails()) $fail($validator->errors()->first());
        // JSON numeric strings and booleans are not numbers in System.Text.Json.
        $numericKeys = ['X','Y','Radius','Field','Strength','OrbitRadius','OrbitSpeed','Phase','Drift','Breath','Width','Speed','ExitSpeed','Facing','Thickness','Swing','Angle','AimAngle','LaunchDelay','ClockStart','ArenaHeight'];
        $integerKeys = ['Version','StarScore','ThreeStarScore','Banks','BankAfterStep','UniqueGoal','SuggestedLevel','Tick','PegId','Score','NearbyClears','Room'];
        $walk = function ($value, bool $root = false) use (&$walk, $numericKeys, $integerKeys, $fail) {
            foreach ($value as $key => $item) {
                if (is_array($item)) { $walk($item); continue; }
                if ($item === null) continue;
                if ($key === 'Id' && !$root && !is_int($item)) $fail('Mechanism IDs must be JSON integers.');
                if (in_array($key, $numericKeys, true) && !is_int($item) && !is_float($item)) $fail("$key must be a JSON number.");
                if (in_array($key, $integerKeys, true) && !is_int($item)) $fail("$key must be a JSON integer.");
            }
        };
        $walk($d, true);
        foreach (['RequiredFields','RequiredGrazes','FinishGrazes','RequiredBumpers'] as $key) {
            foreach ($d[$key] ?? [] as $id) {
                if (!is_int($id) || !in_array($id, array_column($d['Pegs'],'Id'), true)) $fail("$key references a missing peg.");
            }
        }
        foreach (['RequiredGates' => 'Portals','RequiredRivers' => 'Rivers'] as $key => $collection) {
            foreach ($d[$key] ?? [] as $id) if (!is_int($id) || !in_array($id, array_column($d[$collection] ?? [],'Id'), true)) $fail("$key references a missing mechanism.");
        }
        return $d;
    }
    public function mechanics(array $d): array {
        $kinds = array_values(array_unique(array_filter(array_column($d['Pegs'], 'Kind'), fn ($kind) => $kind !== 'Neutral')));
        foreach (['Portals'=>'Portals','Surfaces'=>'Surfaces','Rivers'=>'Rivers','LinkedTargets'=>'Blossoms'] as $key=>$label) if (!empty($d[$key])) $kinds[] = $label;
        return $kinds;
    }
}

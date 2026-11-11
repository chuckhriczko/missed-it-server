@extends('layouts.app')
@section('title', 'Share a puzzle')
@section('content')
<div class="page-heading"><a class="back" href="{{ route('puzzles.index') }}">← Back to the garden</a><p class="eyebrow">A LITTLE CREATION. A WHOLE NEW CHALLENGE.</p><h1>Plant your <em>puzzle.</em></h1><p>Export it from Puzzle Studio. We’ll give it a home and a link to share.</p></div>
<div class="form-layout"><form class="panel" method="post" action="{{ route('puzzles.store') }}" enctype="multipart/form-data">@csrf
    <h2>Bring your board</h2><p class="muted">Choose a JSON file or paste the JSON copied from the game. Maximum 100 KB.</p>
    <label for="puzzle_file">Puzzle JSON file</label><input type="file" id="puzzle_file" name="puzzle_file" accept=".json,application/json"><p class="field-help">Files stay private until you select “Share puzzle.”</p>
    <div class="divider"><span>or paste from your clipboard</span></div>
    <label for="puzzle_json">Puzzle Studio JSON</label><textarea id="puzzle_json" name="puzzle_json" rows="9" maxlength="100000" spellcheck="false" placeholder='{"Version": 1, "Title": "My sky garden", …}'>{{ old('puzzle_json') }}</textarea>
    <label for="difficulty">How does it feel?</label><select id="difficulty" name="difficulty" required>@foreach(['gentle'=>'Gentle · a welcoming little bend','tricky'=>'Tricky · a few happy accidents','expert'=>'Expert · patience makes perfect'] as $value=>$label)<option value="{{ $value }}" @selected(old('difficulty','tricky')===$value)>{{ $label }}</option>@endforeach</select><p class="field-help">Your own difficulty estimate. The server does not solve your board.</p>
    <label for="notes">A note for the next player <span class="muted">(optional)</span></label><textarea name="notes" id="notes" rows="3" maxlength="2000" placeholder="What inspired this garden? Any gentle nudges?">{{ old('notes') }}</textarea>
    <p class="field-help">Your puzzle, creator name, and notes will be public. Your email stays private.</p><button class="button" type="submit">Share puzzle ↗</button>
</form><aside class="panel instructions"><span class="large-flower" aria-hidden="true">✳</span><h2>From Studio to garden</h2><ol><li><strong>Make your route</strong><p>Open Puzzle Studio in Pip and the Last Sunseed. Build a board and test the landing.</p></li><li><strong>Copy your creation</strong><p>In Library, select Share JSON. Paste the copied text here, or save it as a .json file.</p></li><li><strong>Invite someone in</strong><p>Share the puzzle page. Other players can copy or download your JSON and import their own local copy.</p></li></ol><p class="callout">A clear replay is welcome, but optional. Replays are checked by the game when imported.</p></aside></div>
@endsection

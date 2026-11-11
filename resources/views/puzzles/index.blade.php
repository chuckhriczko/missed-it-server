@extends('layouts.app')
@section('content')
<section class="hero">
    <div><p class="eyebrow"><span class="tiny-sun">✳</span> THE COMMUNITY PUZZLE GARDEN</p>
    <h1>{{ request('mine') ? 'Your own little' : 'A new route.' }}<br><em>{{ request('mine') ? 'corner of the garden.' : 'A little possibility.' }}</em></h1>
    <p class="hero-copy">Tricky turns, happy accidents, and one perfect landing.<br>Discover player-made puzzles for Pip — or plant one of your own.</p>
    <div class="hero-actions"><a class="button" href="#collection">Explore the garden <span aria-hidden="true">↓</span></a><a class="subtle-link" href="{{ route('guide') }}">New here? Start with a seed ↗</a></div>
    </div>
    <div class="hero-art" aria-hidden="true"><div class="orbit orbit-one"></div><div class="orbit orbit-two"></div><div class="hero-home">⌂<small>HOME</small></div><div class="hero-peg">✳</div><div class="hero-seed">❧</div><span class="art-note">a bend in the right direction</span><svg viewBox="0 0 460 300"><path d="M80 270 Q 45 60 220 150 T365 65" fill="none" stroke="#7a916c" stroke-width="2" stroke-dasharray="5 7"/></svg></div>
</section>
<section id="collection" class="collection">
    <div class="section-heading"><div><p class="eyebrow">GROWN BY PLAYERS. READY FOR PIP.</p><h2>{{ request('mine') ? 'My puzzles' : 'Find your next “almost.”' }}</h2></div><span class="count">{{ number_format($total) }} {{ Str::plural('garden', $total) }} and growing</span></div>
    <form action="{{ route('puzzles.index') }}" method="get" class="filters" role="search">
        @if(request('mine'))<input type="hidden" name="mine" value="1">@endif
        <div class="search-field"><label class="sr-only" for="q">Search puzzles or creators</label><span aria-hidden="true">⌕</span><input type="search" name="q" id="q" placeholder="Find a puzzle or a creator…" value="{{ request('q') }}" maxlength="100"></div>
        <div><label class="sr-only" for="difficulty">Difficulty</label><select name="difficulty" id="difficulty"><option value="">All difficulties</option>@foreach(['gentle'=>'Gentle','tricky'=>'Tricky','expert'=>'Expert'] as $value=>$label)<option value="{{ $value }}" @selected(request('difficulty')===$value)>{{ $label }}</option>@endforeach</select></div>
        <div><label class="sr-only" for="mechanic">Mechanic</label><select name="mechanic" id="mechanic"><option value="">All mechanics</option>@foreach($mechanics as $mechanic)<option @selected(request('mechanic')===$mechanic)>{{ $mechanic }}</option>@endforeach</select></div>
        <div><label class="sr-only" for="sort">Sort puzzles</label><select name="sort" id="sort"><option value="newest" @selected(request('sort','newest')==='newest')>Newest first</option><option value="popular" @selected(request('sort')==='popular')>Most downloaded</option><option value="oldest" @selected(request('sort')==='oldest')>Oldest first</option></select></div>
        <button class="button small" type="submit">Find puzzles</button>
    </form>
    @if(request()->hasAny(['q','difficulty','mechanic']))<p class="results">{{ $puzzles->total() }} {{ Str::plural('puzzle', $puzzles->total()) }} found · <a href="{{ route('puzzles.index', request('mine') ? ['mine'=>1] : []) }}">Clear filters</a></p>@endif
    <div class="puzzle-grid">
    @forelse($puzzles as $puzzle)
        <article class="puzzle-card">
            <a href="{{ route('puzzles.show', $puzzle) }}" class="preview-link" tabindex="-1" aria-hidden="true"><x-board :board="$puzzle->board()" compact/><span class="preview-label">PEEK INSIDE ↗</span></a>
            <div class="card-body"><div class="card-top"><span class="badge {{ $puzzle->difficulty }}">{{ ucfirst($puzzle->difficulty) }}</span><span class="replay">{{ $puzzle->has_replay ? '↻ Replay included' : '○ Draft puzzle' }}</span></div>
            <h3><a href="{{ route('puzzles.show', $puzzle) }}">{{ $puzzle->title }}</a></h3><p class="card-brief">{{ $puzzle->brief }}</p>
            <div class="tags">@forelse(array_slice($puzzle->mechanics,0,3) as $mechanic)<span>{{ $mechanic }}</span>@empty<span>Pure aim</span>@endforelse</div>
            <div class="card-footer"><span class="creator"><span class="avatar">{{ mb_substr($puzzle->user->name,0,1) }}</span>{{ $puzzle->user->name }}</span><span title="Downloads">↓ {{ number_format($puzzle->downloads) }}</span></div></div>
        </article>
    @empty
        <div class="empty"><span aria-hidden="true">❧</span><h3>{{ request()->hasAny(['q','difficulty','mechanic']) ? 'No gardens along this path.' : 'Every garden starts with a seed.' }}</h3><p>{{ request()->hasAny(['q','difficulty','mechanic']) ? 'Try another search or give a different mechanic a spin.' : 'Share a puzzle from Puzzle Studio and give Pip somewhere new to land.' }}</p><a class="button" href="{{ route('puzzles.create') }}">Share a puzzle ↗</a></div>
    @endforelse
    </div>
    @if($puzzles->hasPages())<nav class="pagination" aria-label="Pagination">@if($puzzles->previousPageUrl())<a class="button secondary" href="{{ $puzzles->previousPageUrl() }}">← Previous</a>@endif<span>Page {{ $puzzles->currentPage() }} of {{ $puzzles->lastPage() }}</span>@if($puzzles->nextPageUrl())<a class="button secondary" href="{{ $puzzles->nextPageUrl() }}">Next →</a>@endif</nav>@endif
</section>
<section class="invitation"><span class="invitation-flower" aria-hidden="true">✳</span><div><p class="eyebrow">YOUR NEXT HAPPY ACCIDENT</p><h2>Made something worth missing?</h2><p>Bring your Puzzle Studio creation to the garden. Someone’s perfect shot is waiting.</p></div><a class="button" href="{{ route('puzzles.create') }}">Plant your puzzle ↗</a></section>
@endsection

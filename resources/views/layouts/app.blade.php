<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'The Puzzle Garden') · Missed It By That Much</title>
    <meta name="description" content="A little room for big trick shots. Discover and share community puzzles for Pip and the Last Sunseed.">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('garden.css') }}">
    <script src="{{ asset('garden.js') }}" defer></script>
</head>
<body>
<header class="site-header"><div class="header-inner">
    <a class="brand" href="{{ route('puzzles.index') }}"><span class="brand-mark" aria-hidden="true">✳</span><span>Missed It<span class="brand-sub">BY THAT MUCH</span></span></a>
    <nav aria-label="Main navigation">
        <a class="{{ request()->routeIs('puzzles.index') && !request('mine') ? 'active' : '' }}" href="{{ route('puzzles.index') }}">Explore</a>
        <a href="{{ route('guide') }}">How it works</a>
        @auth
            <a href="{{ route('puzzles.index', ['mine'=>1]) }}">My puzzles</a>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="text-button" type="submit">Log out</button></form>
        @else
            <a href="{{ route('login') }}">Log in</a>
        @endauth
        <a class="button small" href="{{ route('puzzles.create') }}"><span aria-hidden="true">＋</span> Share a puzzle</a>
    </nav>
</div></header>
<main id="main">
@if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice error" role="alert"><strong>Let’s fix that.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
</main>
<footer><a class="footer-brand" href="{{ route('puzzles.index') }}">✳ A little room for big trick shots.</a><span>Pip and the Last Sunseed · Community Puzzle Studio</span><a href="{{ route('guide') }}">Made to be shared ↗</a></footer>
</body></html>

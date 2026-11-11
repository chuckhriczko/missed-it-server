@extends('layouts.app')
@section('title', 'Connect your game')
@section('content')
<div class="page-heading"><p class="eyebrow">FROM YOUR STUDIO TO THE GARDEN</p><h1>Connect your <em>game.</em></h1><p>Create an API token to let your game share puzzles with this account.</p></div>
<div class="form-layout"><section class="panel">
@if(isset($newToken))
    <div class="notice" role="status"><strong>Your new token is ready.</strong><p>Copy it now. It will not be shown again.</p><label for="new-api-token">API token</label><input type="text" id="new-api-token" value="{{ $newToken }}" readonly spellcheck="false" autocomplete="off"><button type="button" class="button secondary" data-copy="new-api-token">Copy token</button><span class="copy-status" role="status" aria-live="polite"></span></div>
@endif
<h2>Create a token</h2><form method="post" action="{{ route('tokens.store') }}">@csrf<label for="name">Device or application name</label><input name="name" id="name" maxlength="60" value="{{ old('name') }}" placeholder="My desktop game" required><p class="field-help">Tokens can upload and remove your own shared puzzles. Each expires after 90 days. Create up to 10 active tokens.</p><button class="button" type="submit">Create token ↗</button></form>
</section><aside class="panel"><h2>Your tokens</h2>
@forelse($tokens as $token)
<div class="token-row"><strong>{{ $token->name }}</strong><p class="field-help">{{ $token->expires_at?->isPast() ? 'Expired' : 'Expires' }} {{ $token->expires_at?->format('j M Y') ?? 'after 90 days' }} · {{ $token->last_used_at ? 'Last used '.$token->last_used_at->diffForHumans() : 'Not used yet' }}</p><form method="post" action="{{ route('tokens.destroy', $token->id) }}">@csrf @method('DELETE')<button type="submit" class="danger-button">Revoke token</button></form><hr></div>
@empty<p>No tokens yet.</p>@endforelse
<p class="field-help">Treat each token like a password. Give it only to your game and keep it out of puzzle files and public code.</p>
</aside></div>
@endsection

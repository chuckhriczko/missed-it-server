@extends('layouts.app')
@section('title', $register ? 'Join the garden' : 'Welcome back')
@section('content')
<div class="auth-wrap"><p class="eyebrow">THERE’S A LITTLE CREATOR IN EVERYONE.</p><h1>{{ $register ? 'Join the' : 'Welcome' }} <em>{{ $register ? 'garden.' : 'back.' }}</em></h1><p>{{ $register ? 'Keep your creations together and share them with the world.' : 'Your little corner of the garden is waiting.' }}</p>
<form class="panel" method="post" action="{{ $register ? route('register') : route('login') }}">@csrf
    @if($register)<label for="name">Creator name</label><input id="name" name="name" value="{{ old('name') }}" maxlength="40" autocomplete="nickname" required><p class="field-help">This name appears on your public puzzles.</p>@endif
    <label for="email">Email</label><input type="email" id="email" name="email" value="{{ old('email') }}" maxlength="254" autocomplete="email" required>
    <label for="password">Password</label><input type="password" id="password" name="password" autocomplete="{{ $register ? 'new-password' : 'current-password' }}" @if($register) minlength="10" @endif required>
    @if($register)<p class="field-help">At least 10 characters.</p><label for="password_confirmation">Confirm password</label><input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>@else<label class="checkbox"><input type="checkbox" name="remember" value="1"> Remember me</label>@endif
    <button class="button" type="submit">{{ $register ? 'Create account ↗' : 'Log in ↗' }}</button>
</form><p>{{ $register ? 'Already have an account?' : 'New to the garden?' }} <a href="{{ $register ? route('login') : route('register') }}">{{ $register ? 'Log in' : 'Create an account' }}</a></p><a class="subtle-link" href="{{ route('puzzles.index') }}">Just browsing? Explore without an account →</a></div>
@endsection

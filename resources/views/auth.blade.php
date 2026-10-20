@extends('layout')
@section('title', $register ? 'Create your kitchen' : 'Welcome back')
@section('content')
<div class="auth"><div class="eyebrow">A little organisation. A lot less waste.</div><div class="card"><h1>{{ $register ? 'Your kitchen, together.' : 'Welcome back.' }}</h1><p class="muted">{{ $register ? 'Bring your stock, meals and family favourites into one place.' : 'Let’s make this week a little easier.' }}</p>
<form method="post" action="{{ $register ? route('register') : route('login') }}">@csrf
@if($register)<label>Your name<input name="name" autocomplete="name" value="{{ old('name') }}" maxlength="100" required></label>@endif
<label>Email address<input type="email" name="email" autocomplete="email" value="{{ old('email') }}" required></label>
<label>Password<input type="password" name="password" autocomplete="{{ $register ? 'new-password' : 'current-password' }}" @if($register) minlength="10" @endif required></label>
@if($register)<label>Confirm password<input type="password" name="password_confirmation" autocomplete="new-password" required></label><label>Household name<input name="household_name" value="{{ old('household_name', 'Our Kitchen') }}" maxlength="100"></label><details><summary>Joining an existing household?</summary><label>Invitation code<input name="join_code" value="{{ old('join_code') }}" minlength="32" maxlength="32" autocomplete="off"></label><p class="hint">Ask a household member to generate a code from the Family page. Leave blank to create a new household.</p></details>@else<label class="check"><input type="checkbox" name="remember" value="1">Keep me signed in</label>@endif
<button>{{ $register ? 'Create account' : 'Sign in' }}</button></form>
<p class="hint">@if($register)Already have an account? <a href="{{ route('login') }}">Sign in</a>@else New to Kitchen HQ? <a href="{{ route('register') }}">Create your household</a>@endif</p></div></div>
@endsection


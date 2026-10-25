@extends('layout')
@section('title','Settings')
@section('content')
<h1 class="page-title">Settings</h1>

<section class="list settings-group" aria-labelledby="clear-title">
    <h2 id="clear-title" class="list-title">Start fresh</h2>

    <details class="confirm" id="clear-shopping">
        <summary><span><strong>Clear the shopping list</strong><small>{{ $shoppingTotal }} {{ \Illuminate\Support\Str::plural('item', $shoppingTotal) }} on it now</small></span><span class="confirm-open">Clear</span></summary>
        <form method="post" action="{{ route('settings.clear-shopping') }}" class="confirm-panel">@csrf
            <p>Everything on this week's list will be deleted. Things you ran out of are removed too. Your kitchen stays as it is.</p>
            <div class="confirm-actions"><button class="danger-solid" @disabled(!$shoppingTotal)>Yes, clear the list</button><button type="button" class="secondary" data-cancel>Cancel</button></div>
        </form>
    </details>

    @foreach($places as $type=>$label)@php($count = $counts[$type] ?? 0)
    <details class="confirm" id="clear-{{ $type }}">
        <summary><span><strong>Empty the {{ strtolower($label) }}</strong><small>{{ $count }} {{ \Illuminate\Support\Str::plural('item', $count) }}</small></span><span class="confirm-open">Empty</span></summary>
        <form method="post" action="{{ route('settings.clear-kitchen') }}" class="confirm-panel">@csrf<input type="hidden" name="place" value="{{ $type }}">
            <p>All {{ $count }} {{ \Illuminate\Support\Str::plural('item', $count) }} in the {{ strtolower($label) }} will be deleted. They won't be added to the shopping list.</p>
            <div class="confirm-actions"><button class="danger-solid" @disabled(!$count)>Yes, empty the {{ strtolower($label) }}</button><button type="button" class="secondary" data-cancel>Cancel</button></div>
        </form>
    </details>
    @endforeach

    <details class="confirm" id="clear-all">
        <summary><span><strong>Empty the whole kitchen</strong><small>{{ $kitchenTotal }} {{ \Illuminate\Support\Str::plural('item', $kitchenTotal) }} across fridge, freezer and cupboard</small></span><span class="confirm-open">Empty</span></summary>
        <form method="post" action="{{ route('settings.clear-kitchen') }}" class="confirm-panel">@csrf<input type="hidden" name="place" value="all">
            <p>Every item in the fridge, freezer and cupboard will be deleted. This can't be undone.</p>
            <div class="confirm-actions"><button class="danger-solid" @disabled(!$kitchenTotal)>Yes, empty everything</button><button type="button" class="secondary" data-cancel>Cancel</button></div>
        </form>
    </details>
</section>

<section class="list settings-group" aria-labelledby="more-title">
    <h2 id="more-title" class="list-title">More</h2>
    <a class="settings-link" href="{{ route('recipes') }}">Recipes <span aria-hidden="true">›</span></a>
    <a class="settings-link" href="{{ route('family') }}">Family &amp; sharing <span aria-hidden="true">›</span></a>
    <a class="settings-link" href="{{ route('ai') }}">Ask the assistant <span aria-hidden="true">›</span></a>
</section>

<section class="list settings-group">
    <div class="settings-account"><span><strong>{{ auth()->user()->name }}</strong><small>{{ $h->name }} · {{ auth()->user()->email }}</small></span>
        <form method="post" action="{{ route('logout') }}" data-full-page>@csrf<button class="small secondary">Sign out</button></form>
    </div>
</section>
@endsection

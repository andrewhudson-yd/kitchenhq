@extends('layout')
@section('title','Kitchen')
@section('content')
@php($places = \App\Support\FoodDictionary::PLACES)
<h1 class="page-title">Kitchen</h1>

<form class="quick-add" method="post" action="{{ route('stock.add') }}" data-keep-focus autocomplete="off">@csrf
    <div class="quick-add-row">
        <label class="sr-only" for="add-food">Add food</label>
        <input id="add-food" name="name" required maxlength="150" placeholder="Add food, e.g. milk" data-food-suggest data-place-target="place" enterkeyhint="done">
        <button class="add-button" aria-label="Add">@include('ui-icon',['name'=>'plus'])<span>Add</span></button>
    </div>
    <fieldset class="place-picker"><legend class="sr-only">Where does it go?</legend>
        @foreach($places as $type=>$label)<label><input type="radio" name="place" value="{{ $type }}" @checked($place===$type)><span>{{ $label }}</span></label>@endforeach
    </fieldset>
</form>

<div class="place-tabs" role="tablist" aria-label="Where">
    @foreach($places as $type=>$label)
    <a role="tab" href="{{ route('inventory',['place'=>$type]) }}" class="{{ $place===$type ? 'active' : '' }}" aria-selected="{{ $place===$type ? 'true' : 'false' }}">@include('ui-icon',['name'=>$type]){{ $label }} <small>{{ $counts[$type] ?? 0 }}</small></a>
    @endforeach
</div>

<section class="list" aria-label="{{ $places[$place] }}">
@forelse($items as $item)@php($level=\App\Services\SimpleStock::level($item))
<div class="item" id="stock-{{ $item->id }}">
    <div class="item-name"><strong>{{ ucfirst($item->name) }}</strong>@if($level==='low')<small class="low-note">Running low</small>@endif</div>
    <div class="item-actions">
        <form method="post" action="{{ route('stock.level',$item->id) }}">@csrf<input type="hidden" name="level" value="{{ $level==='low' ? 'plenty' : 'low' }}"><button id="low-{{ $item->id }}" class="chip {{ $level==='low' ? 'chip-on' : '' }}" aria-pressed="{{ $level==='low' ? 'true' : 'false' }}" aria-label="{{ $item->name }} running low">Low</button></form>
        <form method="post" action="{{ route('stock.level',$item->id) }}">@csrf<input type="hidden" name="level" value="out"><button id="out-{{ $item->id }}" class="chip chip-out" aria-label="We're out of {{ $item->name }} — add to shopping list">We're out</button></form>
        <details class="more" id="more-{{ $item->id }}"><summary aria-label="More options for {{ $item->name }}">@include('ui-icon',['name'=>'more'])</summary>
            <div class="more-panel">
                <form method="post" action="{{ route('stock.level',$item->id) }}">@csrf<input type="hidden" name="level" value="{{ $level ?? 'plenty' }}">
                    <label>Move to<select name="location_id" data-auto-save>@foreach($locations as $location)<option value="{{ $location->id }}" @selected($item->storage_location_id===$location->id)>{{ $location->name }}</option>@endforeach</select></label><noscript><button class="small secondary">Move</button></noscript>
                </form>
                <form method="post" action="{{ route('stock.remove',$item->id) }}">@csrf @method('delete')<button class="small danger">Remove from kitchen</button></form>
            </div>
        </details>
    </div>
</div>
@empty
<div class="empty"><p><strong>Nothing in the {{ strtolower($places[$place]) }} yet.</strong></p><p class="muted">Type above to add what you have.</p></div>
@endforelse
</section>
<p class="footnote">Tap <strong>We're out</strong> and it moves to your <a href="{{ route('shopping') }}">shopping list</a>.</p>
@include('food-suggestions')
@endsection

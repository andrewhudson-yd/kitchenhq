@extends('layout')
@section('title','What we spotted')
@section('content')
@php($places = \App\Support\FoodDictionary::PLACES)
<h1 class="page-title">What we spotted</h1>
<p class="hint">Untick anything that's wrong, fix names, and choose where each one goes.</p>

<form method="post" action="{{ route('kitchen.photo.add') }}" id="photo-items">@csrf
<section class="list">
    @foreach($items as $i => $item)
    <div class="item photo-item">
        <label class="photo-check"><input type="checkbox" name="items[{{ $i }}][add]" value="1" checked><span class="sr-only">Add {{ $item['name'] }}</span></label>
        <div class="item-name">
            <label class="sr-only" for="photo-name-{{ $i }}">Name</label>
            <input id="photo-name-{{ $i }}" class="photo-name" name="items[{{ $i }}][name]" value="{{ $item['name'] }}" maxlength="150" required>
            @if($item['have'])<small>Already in the kitchen</small>@endif
        </div>
        <label class="sr-only" for="photo-place-{{ $i }}">Where</label>
        <select id="photo-place-{{ $i }}" class="photo-place" name="items[{{ $i }}][place]">@foreach($places as $type=>$label)<option value="{{ $type }}" @selected($item['place']===$type)>{{ $label }}</option>@endforeach</select>
    </div>
    @endforeach
</section>
<div class="photo-actions">
    <button class="photo-add">Add to kitchen</button>
</div>
</form>
<form method="post" action="{{ route('kitchen.photo.cancel') }}" class="center">@csrf<button class="link">Cancel</button></form>
@endsection

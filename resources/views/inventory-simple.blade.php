@extends('layout')
@section('title','Kitchen')
@section('content')
@php($places = \App\Support\FoodDictionary::PLACES)
<h1 class="page-title">Kitchen</h1>

<div class="add-bar">
<form class="quick-add" method="post" action="{{ route('stock.add') }}" data-keep-focus autocomplete="off">@csrf
    <div class="quick-add-row">
        <label class="sr-only" for="add-food">Add food</label>
        <input id="add-food" name="food" required maxlength="150" autocomplete="off" autocorrect="off" placeholder="Add food, e.g. milk" data-food-suggest data-place-target="place" enterkeyhint="done">
        <button class="add-button" aria-label="Add">@include('ui-icon',['name'=>'plus'])<span>Add</span></button>
    </div>
    <fieldset class="place-picker"><legend class="sr-only">Where does it go?</legend>
        @foreach($places as $type=>$label)<label><input type="radio" name="place" value="{{ $type }}" @checked($place===$type)><span>{{ $label }}</span></label>@endforeach
    </fieldset>
</form>
<form class="photo-form" method="post" action="{{ route('kitchen.photo') }}" enctype="multipart/form-data">@csrf<input type="hidden" name="place" value="{{ $place }}">
    <input type="file" id="kitchen-photo" name="photo" accept="image/*" class="sr-only" data-photo-upload required>
    <label for="kitchen-photo" class="camera-button" title="Take a photo and let AI add what it sees">@include('ui-icon',['name'=>'camera'])<span class="sr-only">Take a photo of the {{ strtolower($places[$place]) }}</span></label>
    <noscript><button class="small secondary">Read photo</button></noscript>
</form>
</div>

@if(session('kitchen_removed'))<form method="post" action="{{ route('stock.add') }}" class="undo">@csrf<input type="hidden" name="name" value="{{ session('kitchen_removed.name') }}"><input type="hidden" name="place" value="{{ session('kitchen_removed.place') }}"><input type="hidden" name="level" value="{{ session('kitchen_removed.level') }}"><span>{{ ucfirst(session('kitchen_removed.name')) }} removed.</span><button class="link">Undo</button></form>@endif

<div class="place-tabs" role="tablist" aria-label="Where">
    @foreach($places as $type=>$label)
    <a role="tab" href="{{ route('inventory',['place'=>$type]) }}" class="{{ $place===$type ? 'active' : '' }}" aria-selected="{{ $place===$type ? 'true' : 'false' }}">@include('ui-icon',['name'=>$type]){{ $label }} <small>{{ $counts[$type] ?? 0 }}</small></a>
    @endforeach
</div>

@php($shelves = $items->groupBy(fn ($item) => \App\Support\FoodDictionary::shelfFor($item))->sortBy(fn ($rows, $shelf) => array_search($shelf, \App\Support\FoodDictionary::SHELVES)))
@forelse($shelves as $shelf => $shelfItems)
<section class="list" aria-labelledby="shelf-{{ \Illuminate\Support\Str::slug($shelf) }}">
<h2 id="shelf-{{ \Illuminate\Support\Str::slug($shelf) }}" class="list-title">{{ $shelf }} <small>{{ $shelfItems->count() }}</small></h2>
@foreach($shelfItems as $item)@php($level=\App\Services\SimpleStock::level($item))
<div class="swipe" data-swipe>
<div class="swipe-bg swipe-bg-out" aria-hidden="true">@include('ui-icon',['name'=>'shopping'])Shopping list</div>
<div class="swipe-bg swipe-bg-remove" aria-hidden="true">Remove @include('ui-icon',['name'=>'x'])</div>
<div class="item" id="stock-{{ $item->id }}" data-swipe-right="out-form-{{ $item->id }}" data-swipe-left="remove-form-{{ $item->id }}">
    <div class="item-name"><strong>{{ ucfirst($item->name) }}</strong>@if($level==='low')<small class="low-note">Running low</small>@endif</div>
    <div class="item-actions">
        <form method="post" action="{{ route('stock.level',$item->id) }}">@csrf<input type="hidden" name="level" value="{{ $level==='low' ? 'plenty' : 'low' }}"><button id="low-{{ $item->id }}" class="chip {{ $level==='low' ? 'chip-on' : '' }}" aria-pressed="{{ $level==='low' ? 'true' : 'false' }}" aria-label="{{ $item->name }} running low">Low</button></form>
        <form id="out-form-{{ $item->id }}" method="post" action="{{ route('stock.level',$item->id) }}">@csrf<input type="hidden" name="level" value="out"><button id="out-{{ $item->id }}" class="chip chip-out" aria-label="We're out of {{ $item->name }} — add to shopping list">We're out</button></form>
        <details class="more" id="more-{{ $item->id }}"><summary aria-label="More options for {{ $item->name }}">@include('ui-icon',['name'=>'more'])</summary>
            <div class="more-panel">
                <form method="post" action="{{ route('stock.edit',$item->id) }}" class="edit-form">@csrf
                    <label>Name<input name="name" value="{{ $item->name }}" maxlength="150" required autocomplete="off"></label>
                    <label>Where<select name="location_id">@foreach($locations as $location)<option value="{{ $location->id }}" @selected($item->storage_location_id===$location->id)>{{ $location->name }}</option>@endforeach</select></label>
                    <label>Section<select name="shelf"><option value="">Automatic ({{ \App\Support\FoodDictionary::shelf($item->name, $item->location->type) }})</option>@foreach(\App\Support\FoodDictionary::SHELVES as $option)<option @selected($item->shelf===$option)>{{ $option }}</option>@endforeach</select></label>
                    <button class="small" data-close>Save</button>
                </form>
                <form id="remove-form-{{ $item->id }}" method="post" action="{{ route('stock.remove',$item->id) }}">@csrf @method('delete')<button class="small danger">Remove from kitchen</button></form>
            </div>
        </details>
    </div>
</div>
</div>
@endforeach
</section>
@empty
<section class="list">
<div class="empty"><p><strong>Nothing in the {{ strtolower($places[$place]) }} yet.</strong></p><p class="muted">Type above to add what you have.</p></div>
</section>
@endforelse
<p class="footnote">Swipe right or tap <strong>We're out</strong> to move it to your <a href="{{ route('shopping') }}">shopping list</a>. Swipe left to remove.</p>
@include('food-suggestions')
@endsection

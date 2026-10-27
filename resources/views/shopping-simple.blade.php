@extends('layout')
@section('title','Shopping list')
@section('content')
@php
$all = collect($groups)->flatten(1);
$bought = $all->filter(fn ($row) => $row['reason'] === 'Bought and saved to Stock');
$toBuy = collect($groups['buy'])->reject(fn ($row) => $bought->contains('item_key', $row['item_key']));
$reason = fn ($row) => match (true) {
    $row['reason'] === 'Out' => 'Ran out',
    $row['reason'] === 'Nearly out' => 'Running low',
    $row['reason'] === 'Added by you', str_starts_with($row['reason'], 'Added from') => null,
    str_starts_with($row['uses'][0] ?? '', 'Check ') => $row['uses'][0],
    default => count($row['uses']) && $row['uses'][0] !== 'Stock replenishment' ? 'For '.collect($row['uses'])->take(2)->implode(', ') : null,
};
@endphp
<div class="page-head">
    <h1 class="page-title">Shopping list</h1>
    <span class="count">{{ $toBuy->count() }} to get</span>
</div>
@unless($current)<p class="hint">Week of {{ $week->format('j M') }} · <a href="{{ route('shopping') }}">Back to this week</a></p>@endunless

<div class="add-bar">
<form class="quick-add" method="post" action="{{ route('shopping.add') }}" data-keep-focus autocomplete="off">@csrf
    <div class="quick-add-row">
        <label class="sr-only" for="add-shopping">Add to list</label>
        <input id="add-shopping" name="food" required maxlength="150" autocomplete="off" autocorrect="off" placeholder="Add to the list" data-food-suggest data-quantity enterkeyhint="done">
        <button class="add-button" aria-label="Add to list">@include('ui-icon',['name'=>'plus'])<span>Add</span></button>
    </div>
</form>
<form class="photo-form" method="post" action="{{ route('kitchen.photo') }}" enctype="multipart/form-data">@csrf<input type="hidden" name="target" value="shopping">
    <input type="file" id="shopping-photo" name="photo" accept="image/*" class="sr-only" data-photo-upload required>
    <label for="shopping-photo" class="camera-button" title="Take a photo of things you need">@include('ui-icon',['name'=>'camera'])<span class="sr-only">Take a photo of things you need</span></label>
    <noscript><button class="small secondary">Read photo</button></noscript>
</form>
</div>

@if(session('shopping_got'))<form method="post" action="{{ route('shopping.got') }}" class="undo">@csrf<input type="hidden" name="week" value="{{ session('shopping_got.week') }}"><input type="hidden" name="name" value="{{ session('shopping_got.name') }}"><input type="hidden" name="got" value="0"><span>{{ ucfirst(session('shopping_got.name')) }} is back in the kitchen.</span><button class="link">Undo</button></form>@endif
@if(session('shopping_removed'))<form method="post" action="{{ route('shopping.remove') }}" class="undo">@csrf<input type="hidden" name="week" value="{{ session('shopping_removed.week') }}"><input type="hidden" name="item_key" value="{{ session('shopping_removed.item_key') }}"><input type="hidden" name="removed" value="0"><span>Item removed.</span><button class="link">Undo</button></form>@endif

@php($sections = $toBuy->groupBy(fn ($row) => $picked[\App\Services\SimpleStock::key($row['name'])] ?? \App\Support\FoodDictionary::section($row['name'], $places[\App\Services\SimpleStock::key($row['name'])] ?? null))->sortBy(fn ($rows, $section) => array_search($section, \App\Support\FoodDictionary::SECTIONS)))
@forelse($sections as $section => $rows)
<section class="list" aria-labelledby="section-{{ \Illuminate\Support\Str::slug($section) }}">
    <h2 id="section-{{ \Illuminate\Support\Str::slug($section) }}" class="list-title">{{ $section }} <small>{{ $rows->count() }}</small></h2>
    @foreach($rows as $row)@include('shopping-row',['note'=>$reason($row)])@endforeach
</section>
@empty
<section class="list"><div class="empty"><p><strong>All done.</strong></p><p class="muted">When you run out of something in the kitchen, it lands here.</p></div></section>
@endforelse

@include('shopping-exchange-saved')

<p class="footnote"><a href="{{ route('shopping.photo',['week'=>$week->toDateString()]) }}">Add from a photo of a list</a> · <a href="{{ route('shopping.photo',['week'=>$week->toDateString(),'purpose'=>'receipt']) }}">Scan a receipt</a> · <a href="{{ route('shopping.exchange',['week'=>$week->toDateString()]) }}">Import CSV / JSON</a></p>
@include('food-suggestions')
@endsection

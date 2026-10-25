@extends('layout')
@section('title','Shopping list')
@section('content')
@php
$all = collect($groups)->flatten(1);
$bought = $all->filter(fn ($row) => $row['reason'] === 'Bought and saved to Stock');
$toBuy = collect($groups['buy'])->reject(fn ($row) => $bought->contains('item_key', $row['item_key']));
$maybe = collect($groups['check'])->reject(fn ($row) => $bought->contains('item_key', $row['item_key']));
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

<form class="quick-add" method="post" action="{{ route('shopping.add') }}" data-keep-focus autocomplete="off">@csrf
    <div class="quick-add-row">
        <label class="sr-only" for="add-shopping">Add to list</label>
        <input id="add-shopping" name="food" required maxlength="150" autocomplete="off" autocorrect="off" placeholder="Add to the list, e.g. bread" data-food-suggest enterkeyhint="done">
        <button class="add-button" aria-label="Add to list">@include('ui-icon',['name'=>'plus'])<span>Add</span></button>
    </div>
</form>

@if(session('shopping_removed'))<form method="post" action="{{ route('shopping.remove') }}" class="undo">@csrf<input type="hidden" name="week" value="{{ session('shopping_removed.week') }}"><input type="hidden" name="item_key" value="{{ session('shopping_removed.item_key') }}"><input type="hidden" name="removed" value="0"><span>Item removed.</span><button class="link">Undo</button></form>@endif

<section class="list" aria-labelledby="to-get">
    <h2 id="to-get" class="list-title">To get</h2>
    @forelse($toBuy as $row)@include('shopping-row',['note'=>$reason($row)])@empty
    <div class="empty"><p><strong>All done.</strong></p><p class="muted">When you run out of something in the kitchen, it lands here.</p></div>
    @endforelse
</section>

@if($maybe->count())
<details class="list" id="for-meals">
    <summary class="list-title">For this week's meals <small>{{ $maybe->count() }} · check the kitchen first</small></summary>
    @foreach($maybe as $row)@include('shopping-row',['note'=>$reason($row)])@endforeach
</details>
@endif

@if($bought->count())
<details class="list bought" id="bought">
    <summary class="list-title">Got <small>{{ $bought->count() }} · back in the kitchen</small></summary>
    @foreach($bought as $row)@include('shopping-row',['note'=>null,'done'=>true])@endforeach
</details>
@endif

<p class="footnote"><a href="{{ route('shopping.photo',['week'=>$week->toDateString()]) }}">Add from a photo of a list</a> · <a href="{{ route('shopping.photo',['week'=>$week->toDateString(),'purpose'=>'receipt']) }}">Scan a receipt</a></p>
@include('food-suggestions')
@endsection

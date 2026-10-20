@extends('layout')
@section('title','Shopping')
@section('content')
<div class="section-head"><h1>Shopping</h1><a class="button small" href="{{ route('shopping.photo',['week'=>$week->toDateString()]) }}">+ List photo</a></div>
@include('weekbar',['page'=>'shopping'])
@if(session('shopping_removed'))<form method="post" action="{{ route('shopping.remove') }}" style="margin-bottom:12px">@csrf<input type="hidden" name="week" value="{{ session('shopping_removed.week') }}"><input type="hidden" name="item_key" value="{{ session('shopping_removed.item_key') }}"><input type="hidden" name="removed" value="0"><button class="small secondary">Undo removal</button></form>@endif
@if(count($incomplete))<p class="hint">Ingredient details still needed: {{ implode(', ',$incomplete) }}.</p>@endif
@foreach(['buy'=>'Pick up','check'=>'Check first','have'=>'Already have'] as $action=>$title)
@if($action==='have')<details class="card"><summary>{{ $title }} · {{ count($groups[$action]) }}</summary>@else<section class="card"><h2>{{ $title }} <small>{{ count($groups[$action]) }}</small></h2>@endif
@forelse($groups[$action] as $row)<div class="stock-row shopping-row" id="shop-{{ $row['item_key'] }}"><div class="{{ $row['checked'] ? 'picked-up' : '' }}"><strong>{{ ucfirst($row['name']) }}</strong><small>{{ $row['reason'] }}</small></div>@if($action!=='have')<form method="post" action="{{ route('shopping.check') }}#shop-{{ $row['item_key'] }}">@csrf<input type="hidden" name="week" value="{{ $week->toDateString() }}"><input type="hidden" name="item_key" value="{{ $row['item_key'] }}"><input type="hidden" name="checked" value="{{ $row['checked'] ? '0' : '1' }}"><button class="small {{ $row['checked'] ? 'secondary' : '' }}" aria-label="{{ $row['checked'] ? 'Undo' : 'Got' }} {{ $row['name'] }}">{{ $row['checked'] ? '✓ Undo' : 'Got it' }}</button></form><form method="post" action="{{ route('shopping.remove') }}">@csrf<input type="hidden" name="week" value="{{ $week->toDateString() }}"><input type="hidden" name="item_key" value="{{ $row['item_key'] }}"><input type="hidden" name="removed" value="1"><button class="small danger" aria-label="Remove {{ $row['name'] }}">Remove</button></form>@endif</div>@empty<p class="muted">Nothing here.</p>@endforelse
@if($action==='have')</details>@else</section>@endif
@endforeach
<p class="hint">Ticks are saved. Update Stock after shopping.</p>
@endsection
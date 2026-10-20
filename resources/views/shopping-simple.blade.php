@extends('layout')
@section('title','Shopping')
@section('content')
@php
$all=collect($groups)->flatten(1);
$trolley=$all->filter(fn($row)=>$row['checked'] && $row['reason']!=='Bought and saved to Stock');
$pending=collect($groups['buy'])->merge($groups['check'])->filter(fn($row)=>!$row['checked']);
$have=$all->filter(fn($row)=>$row['action']==='have' && !$trolley->contains('item_key',$row['item_key']));
@endphp
<div class="section-head"><h1>Shopping</h1><a class="text-action" href="{{ route('shopping.photo',['week'=>$week->toDateString()]) }}">+ Add list</a></div>
@include('weekbar',['page'=>'shopping'])
<a class="button finish-shopping" href="{{ route('shopping.photo',['week'=>$week->toDateString(),'purpose'=>'receipt']) }}">Finish shopping · Upload receipt</a>
@if(session('shopping_removed'))<form method="post" action="{{ route('shopping.remove') }}" class="undo-removal">@csrf<input type="hidden" name="week" value="{{ session('shopping_removed.week') }}"><input type="hidden" name="item_key" value="{{ session('shopping_removed.item_key') }}"><input type="hidden" name="removed" value="0"><button class="small secondary">Undo removal</button></form>@endif
<section class="card"><h2>To get <small>{{ $pending->count() }}</small></h2>@forelse($pending as $row)@include('shopping-row',['mode'=>'pending'])@empty<p class="muted">Everything is picked up.</p>@endforelse</section>
<details class="card" id="in-my-trolley"><summary>In my trolley <span class="pill">{{ $trolley->count() }}</span></summary>@forelse($trolley as $row)@include('shopping-row',['mode'=>'trolley'])@empty<p class="hint">Tap Got it as you shop.</p>@endforelse
@if($trolley->count())<form method="post" action="{{ route('purchases.prepare') }}">@csrf<input type="hidden" name="week" value="{{ $week->toDateString() }}"><button class="small secondary">Put away without a receipt</button></form>@endif</details>
@if($have->count())<details class="card" id="already-in-stock"><summary>Already in stock <span class="pill">{{ $have->count() }}</span></summary>@foreach($have as $row)@include('shopping-row',['mode'=>'have'])@endforeach</details>@endif
@endsection
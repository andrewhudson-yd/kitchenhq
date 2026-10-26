@extends('layout')
@section('title','Meals')
@section('content')
<div class="page-head">
    <h1 class="page-title">Meals</h1>
    @include('weekbar',['page'=>'planner'])
</div>

@php($left = collect(range(0,6))->filter(fn ($d) => $week->addDays($d)->gte(today()))->count())
@php($planWeek = $left < 3 && $week->addDays(6)->gte(today()) ? $week->addWeek() : $week)
<form class="plan-ai" method="post" action="{{ route('planner.suggest') }}">@csrf<input type="hidden" name="week" value="{{ $planWeek->toDateString() }}">
    <h2 class="plan-ai-title">@include('ui-icon',['name'=>'sparkle'])Plan {{ $planWeek->equalTo($week) ? 'the week' : 'next week' }} with AI <small>{{ $planWeek->format('j M') }} – {{ $planWeek->addDays(6)->format('j M') }}</small></h2>
    <label class="sr-only" for="meal-note">What do you fancy this week?</label>
    <textarea id="meal-note" name="note" rows="2" maxlength="1000" placeholder="What do you fancy? e.g. quick dinners, one veggie night">{{ old('note') }}</textarea>
    <button class="plan-ai-button">Suggest meals</button>
    <p class="hint">Uses everything in your kitchen and your family's likes and dislikes.</p>
</form>

@php($days = collect(range(0,6))->map(fn($d)=>$week->addDays($d)))
@php($past = $days->filter(fn($d)=>$d->lt(today())))
@php($dayMeals = fn($date) => $meals->filter(fn($m)=>$m->date->toDateString()===$date->toDateString())->sortBy(fn($m)=>str_contains($m->slot,'lunch') ? 0 : 1))
@if($past->count() && $past->count() < 7)
<details class="earlier" id="earlier"><summary>Earlier this week ({{ $past->count() }} {{ \Illuminate\Support\Str::plural('day', $past->count()) }})</summary>
@foreach($past as $date)@include('planner-day')@endforeach
</details>
@foreach($days->diff($past) as $date)@include('planner-day')@endforeach
@else
@foreach($days as $date)@include('planner-day')@endforeach
@endif

@if(!$meals->count())
<form method="post" action="{{ route('planner.generate') }}" class="center">@csrf<input type="hidden" name="week" value="{{ $week->toDateString() }}"><button class="secondary">Fill this week with our usual meals</button></form>
@endif

<details class="panel" id="change-meal"><summary>Plan or change a meal</summary>
<form method="post" action="{{ route('meals.store') }}">@csrf
    <div class="form-grid">
        <label>Day<input type="date" name="date" value="{{ ($week <= today() && today() <= $week->addDays(6)) ? today()->toDateString() : $week->toDateString() }}" required></label>
        <label>Meal<select name="slot"><option value="late_dinner">Adult dinner</option><option value="early_dinner">Earlier dinner</option><option value="lunch">Lunch</option><option value="packed_lunch">Packed lunch</option></select></label>
        <label>For<select name="audience"><option>Adults</option><option>Noah</option><option>You</option><option>Everyone</option></select></label>
        <label>Servings<input type="number" name="servings" value="2" min="1" max="20" required></label>
        <label class="full">What<select name="recipe_id">@foreach($recipes as $recipe)<option value="{{ $recipe->id }}">{{ $recipe->name }}</option>@endforeach</select></label>
    </div>
    <button>Save meal</button>
</form>
<p class="hint">Meal ingredients you don't have show up on the <a href="{{ route('shopping',['week'=>$week->toDateString()]) }}">shopping list</a>. <a href="{{ route('recipes') }}">Edit recipes</a></p>
</details>
@endsection

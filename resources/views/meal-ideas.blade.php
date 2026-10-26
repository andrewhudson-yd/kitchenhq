@extends('layout')
@section('title','Meal ideas')
@section('content')
<h1 class="page-title">Meal ideas</h1>
<p class="hint">@if($note)“{{ $note }}” · @endif Tick the meals you'd like, and change the day or who it's for.</p>

<form method="post" action="{{ route('planner.ideas.plan') }}">@csrf
@foreach($ideas as $i => $idea)
<section class="idea {{ $idea['pick'] ? 'is-picked' : '' }}">
    <label class="idea-pick"><input type="checkbox" name="ideas[{{ $i }}][pick]" value="1" @checked(old("ideas.$i.pick", $idea['pick']))><span class="sr-only">Plan {{ $idea['name'] }}</span></label>
    <div class="idea-body">
        <h2>{{ $idea['name'] }}</h2>
        @if($idea['description'])<p>{{ $idea['description'] }}</p>@endif
        @if($idea['uses'])<p class="idea-uses"><strong>Uses</strong> {{ implode(', ', $idea['uses']) }}</p>@endif
        @if($idea['needs'])<p class="idea-needs"><strong>Need to buy</strong> {{ implode(', ', $idea['needs']) }}</p>@endif
        <div class="idea-when">
            <label><span class="sr-only">Day</span><select name="ideas[{{ $i }}][day]">@foreach($days as $day)<option value="{{ $day }}" @selected(old("ideas.$i.day", $idea['day'])===$day)>{{ \Carbon\CarbonImmutable::parse($day)->format('D j M') }}</option>@endforeach</select></label>
            <label><span class="sr-only">For</span><select name="ideas[{{ $i }}][for]">@foreach($audiences as $audience)<option @selected(old("ideas.$i.for", $idea['for'])===$audience)>{{ $audience }}</option>@endforeach</select></label>
        </div>
        @php($current = $existing[$idea['day'].'|'.\App\Http\Controllers\MealIdeasController::AUDIENCES[$idea['for']].'|'.$idea['for']] ?? null)
        @if($current)<p class="idea-replaces">Replaces {{ $current }}</p>@endif
    </div>
</section>
@endforeach
<label class="check idea-shop"><input type="checkbox" name="shop" value="1" checked> Add the things to buy to my shopping list</label>
<div class="photo-actions"><button class="photo-add">Plan these meals</button></div>
</form>
<form method="post" action="{{ route('planner.ideas.cancel') }}" class="center">@csrf<button class="link">Cancel</button></form>
@endsection

<section class="day {{ $date->isToday() ? 'is-today' : '' }}" id="day-{{ $date->toDateString() }}">
    <h2 class="day-title"><span>{{ $date->isToday() ? 'Today' : $date->format('l') }}</span><small>{{ $date->format('j M') }}</small></h2>
    @forelse($dayMeals($date) as $meal)
    <div class="meal-row {{ $meal->cooked_at ? 'is-done' : '' }}">
        <span class="meal-slot">{{ str_contains($meal->slot,'lunch') ? 'Lunch' : 'Dinner' }}</span>
        <strong>{{ $meal->recipe->name }}</strong>
        <span class="meal-for">{{ $meal->audience }}</span>
        @unless($meal->cooked_at)<form method="post" action="{{ route('meals.destroy',$meal->id) }}">@csrf @method('delete')<button class="icon-button meal-delete" aria-label="Delete {{ $meal->recipe->name }} on {{ $date->format('l') }}">@include('ui-icon',['name'=>'x'])</button></form>@endunless
    </div>
    @empty
    <p class="muted">Nothing planned.</p>
    @endforelse
</section>

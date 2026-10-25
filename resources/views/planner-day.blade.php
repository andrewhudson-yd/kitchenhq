<section class="day {{ $date->isToday() ? 'is-today' : '' }}" id="day-{{ $date->toDateString() }}">
    <h2 class="day-title"><span>{{ $date->isToday() ? 'Today' : $date->format('l') }}</span><small>{{ $date->format('j M') }}</small></h2>
    @forelse($dayMeals($date) as $meal)
    <div class="meal-row {{ $meal->cooked_at ? 'is-done' : '' }}">
        <span class="meal-slot">{{ str_contains($meal->slot,'lunch') ? 'Lunch' : 'Dinner' }}</span>
        <strong>{{ $meal->recipe->name }}</strong>
        <span class="meal-for">{{ $meal->audience }}</span>
    </div>
    @empty
    <p class="muted">Nothing planned.</p>
    @endforelse
</section>

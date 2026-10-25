@php($done = $done ?? false)
<div class="item shop-item {{ $done ? 'is-done' : '' }}" id="shop-{{ $row['item_key'] }}">
    <form method="post" action="{{ route('shopping.got') }}">@csrf<input type="hidden" name="week" value="{{ $week->toDateString() }}"><input type="hidden" name="name" value="{{ $row['name'] }}"><input type="hidden" name="got" value="{{ $done ? 0 : 1 }}">
        <button id="got-{{ $row['item_key'] }}" class="tick" aria-pressed="{{ $done ? 'true' : 'false' }}" aria-label="{{ $done ? 'Put '.$row['name'].' back on the list' : 'Got '.$row['name'] }}">@include('ui-icon',['name'=>'check'])</button>
    </form>
    <div class="item-name"><strong>{{ ucfirst($row['name']) }}</strong>@if($note)<small>{{ $note }}</small>@endif</div>
    @php($qty = $row['quantity'] ?? null)
    @if($done)@if($qty)<span class="qty-pill">{{ \App\Support\ShoppingQuantity::label($qty) }}</span>@endif
    @else
    <details class="qty" id="qty-{{ $row['item_key'] }}">
        <summary class="qty-pill {{ $qty ? '' : 'is-empty' }}" aria-label="{{ $qty ? 'Quantity '.$qty.' — change' : 'Set quantity for '.$row['name'] }}">{{ $qty ? \App\Support\ShoppingQuantity::label($qty) : 'Qty' }}</summary>
        <div class="qty-panel">
            <form method="post" action="{{ route('shopping.quantity') }}" class="qty-form">@csrf<input type="hidden" name="week" value="{{ $week->toDateString() }}"><input type="hidden" name="item_key" value="{{ $row['item_key'] }}">
                <button class="small qty-done" data-close>Done</button>
                <button name="step" value="-1" class="qty-step" aria-label="One less">−</button>
                <label class="sr-only" for="qty-input-{{ $row['item_key'] }}">Quantity for {{ $row['name'] }}</label>
                <input id="qty-input-{{ $row['item_key'] }}" name="quantity" value="{{ $qty ?? '1' }}" maxlength="30" autocomplete="off" placeholder="e.g. 2 or 500g">
                <button name="step" value="1" class="qty-step" aria-label="One more">+</button>
            </form>
        </div>
    </details>
    @endif
    @unless($done)<form method="post" action="{{ route('shopping.remove') }}">@csrf<input type="hidden" name="week" value="{{ $week->toDateString() }}"><input type="hidden" name="item_key" value="{{ $row['item_key'] }}"><input type="hidden" name="removed" value="1"><button class="icon-button" aria-label="Remove {{ $row['name'] }}">@include('ui-icon',['name'=>'x'])</button></form>@endunless
</div>

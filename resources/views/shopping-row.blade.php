@php($done = $done ?? false)
<div class="item shop-item {{ $done ? 'is-done' : '' }}" id="shop-{{ $row['item_key'] }}">
    <form method="post" action="{{ route('shopping.got') }}">@csrf<input type="hidden" name="week" value="{{ $week->toDateString() }}"><input type="hidden" name="name" value="{{ $row['name'] }}"><input type="hidden" name="got" value="{{ $done ? 0 : 1 }}">
        <button id="got-{{ $row['item_key'] }}" class="tick" aria-pressed="{{ $done ? 'true' : 'false' }}" aria-label="{{ $done ? 'Put '.$row['name'].' back on the list' : 'Got '.$row['name'] }}">@include('ui-icon',['name'=>'check'])</button>
    </form>
    <div class="item-name"><strong>{{ ucfirst($row['name']) }}</strong>@if($note)<small>{{ $note }}</small>@endif</div>
    @unless($done)<form method="post" action="{{ route('shopping.remove') }}">@csrf<input type="hidden" name="week" value="{{ $week->toDateString() }}"><input type="hidden" name="item_key" value="{{ $row['item_key'] }}"><input type="hidden" name="removed" value="1"><button class="icon-button" aria-label="Remove {{ $row['name'] }}">@include('ui-icon',['name'=>'x'])</button></form>@endunless
</div>

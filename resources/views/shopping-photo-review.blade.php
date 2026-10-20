@extends('layout')
@section('title','Check your list')
@section('content')
<div class="section-head"><h1>Check your list</h1><a href="{{ route('shopping',['week'=>$draft->week]) }}">Back</a></div>
@if($draft->imported_at)<div class="notice success">This photo has already been added. <a href="{{ route('shopping',['week'=>$draft->week]) }}">Open shopping list</a></div>@else
<div class="photo-review"><section class="card"><img class="list-photo" src="{{ route('shopping.photo.image',$draft->id) }}" alt="Your uploaded shopping list"><form method="post" action="{{ route('shopping.photo.read',$draft->id) }}">@csrf<p class="hint">Read photo sends this image to OpenAI using the server connection. Check the result before adding items. API charges may apply.</p><button class="small" @disabled(!$connected)>Read photo</button>@if(!$connected)<a href="{{ route('shopping.photo',['week'=>$draft->week]) }}">Check photo reading setup</a>@endif</form></section><section class="card"><form method="post" action="{{ route('shopping.photo.import',$draft->id) }}">@csrf<label>Items · one per line<textarea name="items" rows="12" maxlength="16000" required placeholder="Milk&#10;Bread&#10;Apples">{{ old('items',$items) }}</textarea></label><p class="hint">Edit the words or type the list yourself. Keep written quantities if useful. Remove items you don’t want.</p><button>Add to shopping list</button></form></section></div>
@endif
@endsection

@extends('layout')
@section('title','Check purchases')
@section('content')
<div class="section-head"><h1>Check purchases</h1><a href="{{ route('shopping',['week'=>$batch->week]) }}">Back</a></div>
@if($batch->shopping_photo_draft_id)<details class="card"><summary>View receipt</summary><img class="list-photo" src="{{ route('shopping.photo.image',$batch->shopping_photo_draft_id) }}" alt="Your shopping receipt"></details>@endif
@if($batch->applied_at)<p class="notice success">Already saved to Stock. Nothing will be added twice.</p>@else
<p class="hint">Check names and locations, then save. Untick anything you did not buy.</p>
<form method="post" action="{{ route('purchases.save',$batch->id) }}">@csrf
@foreach($rows as $i=>$row)<section class="card"><label class="check"><input type="checkbox" name="rows[{{ $i }}][include]" value="1" @checked(old("rows.$i.include",!$row['ambiguous']))> {{ $row['source'] }}</label>
<div class="form-grid"><label>Item<input name="rows[{{ $i }}][name]" value="{{ old("rows.$i.name",$row['name']) }}" required maxlength="150"></label><label>Put in<select name="rows[{{ $i }}][location]">@foreach(['pantry'=>'Cupboard','fridge'=>'Fridge','freezer'=>'Freezer'] as $type=>$label)<option value="{{ $type }}" @selected(old("rows.$i.location",$row['location'])===$type)>{{ $label }}</option>@endforeach</select></label></div>
<details id="purchase-match-{{ $i }}" @if($row['ambiguous']) open @endif><summary>{{ $row['inventory_id'] ? 'Updating existing stock' : 'New item / choose an existing match' }}{{ $row['shopping_key'] ? ' · list match found' : '' }}</summary><label>Stock entry<select name="rows[{{ $i }}][inventory_id]"><option value="">New item / match by name</option>@foreach($inventory as $stock)<option value="{{ $stock->id }}" @selected((string)old("rows.$i.inventory_id",$row['inventory_id'])===(string)$stock->id)>{{ ucfirst($stock->name) }}</option>@endforeach</select></label><label>Mark on shopping list<select name="rows[{{ $i }}][shopping_key]"><option value="">No list match</option>@foreach($shopping as $key=>$entry)<option value="{{ $key }}" @selected(old("rows.$i.shopping_key",$row['shopping_key'])===$key)>{{ ucfirst($entry['name']) }}</option>@endforeach</select></label></details>
@if($row['ambiguous'])<p class="hint">Several stock entries could match. Choose the correct entry and tick to include it.</p>@endif</section>@endforeach
<button>Save & put away</button></form>@endif
@endsection

@foreach($payload['recipes'] as $recipe)
@php($ingredients=collect($payload['ingredients'])->filter(fn($row)=>$row['recipe_id']===$recipe['recipe_id'] && $row['meal_date']===$recipe['meal_date'] && $row['meal_type']===$recipe['meal_type']))
<section class="exchange-recipe"><h3>{{ $recipe['recipe_name'] }}</h3><p class="hint">{{ $recipe['meal_date'] }} · {{ $recipe['meal_type'] }} · {{ $recipe['servings'] }} servings for this recipe</p>
@foreach($ingredients as $ingredient)
<article class="exchange-row"><div class="exchange-row-title"><strong>{{ $ingredient['ingredient_name'] }}</strong><span class="pill {{ $ingredient['inventory_availability']==='have' ? '' : 'warn' }}">{{ ['have'=>'Reported in stock','buy'=>'Purchase requested','check'=>'Check stock'][$ingredient['inventory_availability']] }}</span></div>
<p><strong>Recipe needs:</strong> {{ $ingredient['quantity'] === null ? 'To taste' : \App\Support\Food::amount($ingredient['quantity']).' '.$ingredient['unit'] }} @if($ingredient['category'])<span class="muted">· {{ $ingredient['category'] }}</span>@endif</p>
<p class="hint">@if($ingredient['inventory_quantity_confirmed'])Reported measured stock: {{ \App\Support\Food::amount($ingredient['inventory_quantity']) }} {{ $ingredient['inventory_unit'] }} (reference only; not written to Stock). @elseif($ingredient['reported_inventory_min'] !== null)Reported estimate: {{ \App\Support\Food::amount($ingredient['reported_inventory_min']) }}–{{ \App\Support\Food::amount($ingredient['reported_inventory_max']) }} {{ $ingredient['inventory_unit'] }} · unconfirmed. @else Stock quantity unknown. @endif
Purchase quantity: {{ $ingredient['purchase_quantity'] === null ? 'not calculated or confirmed' : \App\Support\Food::amount($ingredient['purchase_quantity']).' '.$ingredient['unit'].' (from file; not recorded as bought)' }}.</p>
@if($ingredient['notes'])<p class="hint exchange-note">{{ $ingredient['notes'] }}</p>@endif</article>
@endforeach</section>
@endforeach

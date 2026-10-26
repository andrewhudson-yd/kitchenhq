@extends('layout')
@section('title','Import meal ingredients')
@section('content')
<div class="section-head"><h1>Import ingredients</h1><a href="{{ route('shopping',['week'=>$week]) }}">Back</a></div>
<section class="card"><h2>CSV or JSON</h2><p>Bring a meal’s ingredients into your shopping page. You’ll see every item before saving.</p>
<form data-full-page method="post" enctype="multipart/form-data" action="{{ route('shopping.exchange.prepare') }}">@csrf
<label>Shopping week starting<input type="date" name="week" value="{{ old('week',$week) }}" required></label>
<label>Format<select name="format"><option value="auto" @selected(old('format')==='auto')>Detect automatically</option><option value="json" @selected(old('format')==='json')>JSON</option><option value="csv" @selected(old('format')==='csv')>CSV</option></select></label>
<label>Choose a file<input type="file" name="exchange_file" accept=".csv,.json,.txt,text/csv,application/json,text/plain"></label>
<p class="hint">Or paste the complete JSON or CSV below. Use one input at a time. UTF-8, up to 512 KB and 300 ingredient rows.</p>
<label>Paste JSON or CSV<textarea name="exchange_text" rows="9" spellcheck="false" placeholder="Paste your meal ingredient exchange here…">{{ old('exchange_text') }}</textarea></label>
<button>Preview ingredients</button></form></section>
<section class="card"><h2>Use your exchange format</h2><p>Supports “Standalone meal ingredient exchange”, version 1.0. CSV uses its field names as comma-separated column headings. Leave unknown values empty; use lowercase <code>false</code> for unconfirmed quantities.</p><p><a download href="{{ route('shopping.exchange.example','json') }}">Download example JSON</a> · <a download href="{{ route('shopping.exchange.example','csv') }}">Download example CSV</a></p><p class="hint">The examples contain your paella and Eton Mess ingredients. Recipe quantities stay separate from reported stock and purchase amounts. No stock balances, meal-plan slots or purchases are changed by this import.</p></section>
@endsection


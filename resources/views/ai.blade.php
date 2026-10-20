@extends('layout')
@section('title','Kitchen assistant')
@section('content')
<div class="eyebrow">A helping hand in your kitchen</div><h1>Kitchen assistant</h1><p>Ask about tonight’s dinner, using up freezer stock, or your shopping list. Suggestions leave your saved meals and stock unchanged.</p>
<section class="card"><h2>OpenAI connection</h2><p>{{ $connected ? 'API key saved. Ready to try a question.' : 'Add your OpenAI API key to get started.' }}</p><p class="hint">This connection is managed with OPENAI_API_KEY and OPENAI_MODEL in the server .env. API usage is billed separately.</p></section>
<section class="card"><h2>Ask your kitchen</h2><p>When you press Ask, your question, stock, kit names, this week’s meals, food preferences and shopping list are sent to OpenAI. Login details and your password are excluded.</p><form method="post" action="{{ route('ai.ask') }}">@csrf<label>Your question<textarea name="question" required maxlength="2000" placeholder="What should I buy this week, and what can I use from the freezer?">{{ old('question') }}</textarea></label><button @disabled(!$connected)>Ask kitchen assistant</button></form></section>
@if($answer)<section class="card"><h2>Your kitchen assistant says</h2><div style="white-space:pre-wrap">{{ $answer }}</div></section>@endif
@endsection

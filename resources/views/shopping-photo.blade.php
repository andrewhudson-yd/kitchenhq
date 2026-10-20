@extends('layout')
@section('title','Add a list photo')
@section('content')
<div class="section-head"><h1>Add a list photo</h1><a href="{{ route('shopping',['week'=>$week]) }}">Back</a></div>
<section class="card"><form method="post" enctype="multipart/form-data" action="{{ route('shopping.photo.upload') }}">@csrf<input type="hidden" name="week" value="{{ $week }}"><label>Choose a photo<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></label><p class="hint">JPG, PNG or WebP, up to 5 MB. You’ll check the items before adding them.</p><button>Upload photo</button></form><details><summary>Use phone camera instead</summary><form method="post" enctype="multipart/form-data" action="{{ route('shopping.photo.upload') }}">@csrf<input type="hidden" name="week" value="{{ $week }}"><label>Take a photo<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="environment" required></label><button>Upload camera photo</button></form></details></section>
<p class="hint">{{ $connected ? 'Automatic photo reading is configured on the server.' : 'Automatic reading needs OPENAI_API_KEY in the server .env. You can still upload and type the items.' }}</p>
@endsection

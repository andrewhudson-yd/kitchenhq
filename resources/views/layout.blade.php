<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#285f49">
<title>@yield('title', 'Kitchen HQ') · Kitchen HQ</title>
<link rel="icon" href="{{ asset('brand/kitchen-mark.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="{{ asset('kitchen.css').'?v=crisp-20261028-merge1' }}">
<script src="{{ asset('kitchen.js').'?v=crisp-20261028-merge1' }}" defer></script>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="top">
    <a class="brand" href="{{ route('dashboard') }}" aria-label="Kitchen HQ home"><img class="brand-mark" src="{{ asset('brand/kitchen-mark.svg') }}" width="45" height="45" alt=""><span class="brand-name">Kitchen <span>HQ</span></span></a>
    <div class="brand-context"><small>Household kitchen</small><strong>@auth{{ auth()->user()->household?->name }}@else Less waste. Better days.@endauth</strong></div>
</header>
@auth
<nav class="tabs-main" aria-label="Main navigation">
    @foreach(['inventory'=>'Kitchen','shopping'=>'Shopping','planner'=>'Meals','settings'=>'Settings'] as $route=>$label)
    <a class="{{ request()->routeIs($route) ? 'active' : '' }}" href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif>@include('ui-icon',['name'=>$route])<span>{{ $label }}</span></a>
    @endforeach
</nav>
@endauth
<main id="main">
@if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice error" role="alert"><strong>Please check the following</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
</main>
</body>
</html>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#f7f5f0">
<title>@yield('title', 'Kitchen HQ') · Kitchen HQ</title>
<link rel="stylesheet" href="{{ asset('kitchen.css').'?v=fresh11' }}">
<script src="{{ asset('kitchen.js').'?v=fresh11' }}" defer></script>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="top">
    <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark" aria-hidden="true">K</span>Kitchen HQ</a>
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

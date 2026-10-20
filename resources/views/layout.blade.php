<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#193f36"><title>@yield('title', 'Kitchen HQ') · Kitchen HQ</title><link rel="stylesheet" href="{{ asset('kitchen.css').'?v=premium4' }}"><script src="{{ asset('kitchen.js').'?v=1' }}" defer></script></head>
<body class="compact-app"><a class="skip" href="#main">Skip to content</a><header class="top"><a class="brand" href="{{ route('dashboard') }}">Kitchen <span>HQ</span></a>@auth <div class="account"><span>{{ auth()->user()->household?->name }}</span><form method="post" action="{{ route('logout') }}">@csrf<button class="quiet" aria-label="Sign out">@include('ui-icon',['name'=>'logout'])<span>Sign out</span></button></form></div>@endauth</header>
@auth<nav aria-label="Main navigation">@foreach(['inventory'=>'Stock','planner'=>'Meals','shopping'=>'Shopping'] as $route=>$label)<a class="{{ request()->routeIs($route) ? 'active' : '' }}" href="{{ route($route) }}" @if(request()->routeIs($route)) aria-current="page" @endif>@include('ui-icon',['name'=>$route])<span>{{ $label }}</span></a>@endforeach</nav>@endauth
<main id="main">@if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice error" role="alert"><strong>Please check the following</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')</main><footer>Kitchen HQ <span>Less waste. A calmer week. More room for life.</span></footer></body></html>




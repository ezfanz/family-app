@extends('layouts.app')

@section('body')
<header class="top"><div class="top-in">
  <div class="title-row">
    <h1>@yield('heading', 'Japan Trip')</h1>
    @auth
      <nav class="top-nav" aria-label="Account">
        <a href="{{ route('trips.index') }}">Trips</a>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit">Log out</button>
        </form>
      </nav>
    @endauth
  </div>
</div></header>

<main class="page">
  @if (session('status'))
    <div class="flash" role="status">{{ session('status') }}</div>
  @endif
  @yield('content')
</main>
@endsection

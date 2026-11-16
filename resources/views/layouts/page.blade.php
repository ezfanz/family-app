@extends('layouts.app')

@section('body')
<div class="shell no-nav">
  @hasSection('back')
    <header class="back-header">
      <a class="back-link" href="@yield('back')"><svg class="i" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>@yield('back-label', 'Back')</a>
    </header>
  @else
    <header class="simple-header">
      <span class="brand"><img src="/icons/icon.svg" alt="" width="28" height="28">E-Itinerary</span>
      <div class="aside">
        @auth
          @hasSection('aside')
            @yield('aside')
          @else
            <a href="{{ route('trips.index') }}">Trips</a>
          @endif
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Log out</button>
          </form>
        @else
          @yield('aside')
        @endauth
      </div>
    </header>
  @endif

  <main class="page">
    <div class="narrow">
      @if (session('status'))
        <div class="flash" role="status">{{ session('status') }}</div>
      @endif
      @yield('content')
    </div>
  </main>
</div>
@endsection

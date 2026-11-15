@extends('layouts.page')

@section('title', 'Your trips · Japan Trip')

@section('content')
<h2 class="sec">Your trips</h2>

@if ($trips->isEmpty())
  <p class="sub">You’re not in a trip yet. Ask the organiser for the join code.</p>
@else
  <div class="panel">
    <ul class="rows">
      @foreach ($trips as $trip)
        <li>
          <a class="trip-link" href="{{ route('trips.show', $trip) }}">
            <strong>{{ $trip->name }}</strong>
          </a>
          <span class="role {{ $trip->pivot->role->value }}">{{ $trip->pivot->role->value }}</span>
        </li>
      @endforeach
    </ul>
  </div>
@endif

<div class="panel">
  <h3>Join a trip</h3>
  <form class="form" method="POST" action="{{ route('trips.join') }}" novalidate>
    @csrf
    <label>Join code <span class="hint">8 letters and numbers</span>
      <input name="code" type="text" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="12" value="{{ old('code') }}" required
        @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
    </label>
    @error('code')<span class="field-error" id="code-error">{{ $message }}</span>@enderror
    <button type="submit" class="btn primary">Join</button>
  </form>
</div>
@endsection

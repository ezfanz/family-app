@extends('layouts.page')

@section('title', 'Trips · Tabi')

@section('content')
@if ($trips->isNotEmpty())
  <h1>Your trips</h1>
  <ul class="list" style="margin-top:16px">
    @foreach ($trips as $trip)
      <li>
        <a class="trip-row" href="{{ route('trips.show', $trip) }}">
          <span>
            <strong>{{ $trip->name }}</strong>
            <span class="tnum">{{ $trip->start_date->format('j M') }} – {{ $trip->end_date->format('j M Y') }}</span>
          </span>
          <span class="role-chip">{{ $trip->pivot->role->value }}</span>
        </a>
      </li>
    @endforeach
  </ul>
  <h2 class="sec">Join another trip</h2>
@else
  <h1>Join a trip</h1>
  <p class="lede" style="font-size:16px;line-height:1.55;margin-top:8px">Ask the trip owner for the 8-character join code.</p>
@endif

<form method="POST" action="{{ route('trips.join') }}" novalidate style="margin-top:20px">
  @csrf
  <label for="code" class="field" style="display:block"><span style="font-size:15px;font-weight:700">Join code</span></label>
  <input id="code" name="code" class="code-input tnum" type="text" maxlength="12" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="ABCD1234" value="{{ old('code') }}" required
    aria-describedby="code-hint @error('code') code-error @enderror" @error('code') aria-invalid="true" @enderror>
  <p class="muted" id="code-hint" style="margin:8px 0 0">Letters and numbers. Capitals don’t matter.</p>
  @error('code')<span class="field-error" id="code-error">{{ $message }}</span>@enderror
  <button type="submit" class="btn primary" style="margin-top:28px">Join trip</button>
  <p class="center-note">You’ll see the plan right away. The owner decides who can edit.</p>
</form>

<div class="or">or</div>
<a class="btn" href="{{ route('trips.create') }}" style="margin-top:16px">Start a new trip</a>
@endsection

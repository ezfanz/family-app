@extends('layouts.page')

@section('title', 'Account · E-Itinerary')
@section('back', $back)
@section('back-label', $backLabel)

@section('content')
<h1>Account</h1>

<div class="acct-card" style="margin-top:16px">
  <div class="acct-row">
    <span class="avatar solid" aria-hidden="true">{{ Str::upper(Str::substr($user->name, 0, 2)) }}</span>
    <span class="acct-who"><strong>{{ $user->name }}</strong><span>{{ $user->email }}</span></span>
  </div>
</div>

<h2 class="acct-h">Trips</h2>
<div class="acct-card">
  @foreach ($trips as $trip)
    <a class="acct-row link" href="{{ route('trips.show', $trip) }}">
      <span class="acct-who">
        <strong>{{ $trip->name }}</strong>
        <span class="tnum">{{ $trip->start_date->format('j M') }} – {{ $trip->end_date->format('j M') }} · {{ ucfirst($trip->pivot->role->value) }}</span>
      </span>
      @if ($trip->id === $currentTripId)
        <span class="chip-current">Current</span>
      @endif
    </a>
  @endforeach
  <a class="acct-row link action" href="{{ route('trips.index') }}">
    <svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
    <span>Join or start another trip</span>
  </a>
</div>

<h2 class="acct-h">Preferences</h2>
<div class="acct-card">
  <fieldset class="plain acct-pref">
    <legend>Appearance</legend>
    <div class="seg seg-pref" role="radiogroup">
      @foreach (['light' => 'Light', 'dark' => 'Dark', 'system' => 'Auto'] as $value => $label)
        <label class="seg-opt">
          <input type="radio" name="theme" value="{{ $value }}" data-label="{{ $label }}" @checked($theme === $value)>
          <span>{{ $label }}</span>
        </label>
      @endforeach
    </div>
    <p class="sr-only" id="themeStatus" role="status" aria-live="polite"></p>
  </fieldset>

  <label class="acct-pref switch-row" for="offlineCopy">
    <span class="acct-who">
      <strong>Save trip on this phone</strong>
      <span>See the plan without signal</span>
    </span>
    <span class="switch">
      <input type="checkbox" id="offlineCopy" role="switch" checked>
      <span class="track" aria-hidden="true"><span class="thumb"></span></span>
    </span>
  </label>
</div>

<form id="logoutForm" method="POST" action="{{ route('logout') }}" style="margin-top:20px">
  @csrf
  <button type="submit" class="btn logout-btn" data-confirm-logout>
    <svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 17l-5-5 5-5M5 12h11"/></svg>
    Log out
  </button>
</form>
<p class="muted" style="text-align:center;margin-top:14px">E-Itinerary 1.0</p>

<dialog class="sheet confirm-sheet" id="logoutDlg" aria-labelledby="logoutTitle">
  <div class="grab" aria-hidden="true"></div>
  <div class="confirm-body">
    <span class="confirm-icon" aria-hidden="true"><svg class="i" width="20" height="20" viewBox="0 0 24 24"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 17l-5-5 5-5M5 12h11"/></svg></span>
    <h2 id="logoutTitle">Log out of E-Itinerary?</h2>
    <p>@if ($trips->isNotEmpty()){{ $trips->pluck('name')->join(', ') }} and your notes stay saved with the group.@else Your account stays saved.@endif Sign in again any time to see them.</p>
    <div class="confirm-note">
      <svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"/><path d="M8.5 8.6A5 5 0 0 0 6.5 18H17"/><path d="M10.6 6.2A5 5 0 0 1 17.6 10 3.5 3.5 0 0 1 20 16"/></svg>
      The offline copy on this phone will be removed.
    </div>
    <button type="submit" form="logoutForm" class="btn danger-solid">Log out</button>
    <button type="button" class="btn" data-close autofocus>Stay signed in</button>
  </div>
</dialog>
@endsection

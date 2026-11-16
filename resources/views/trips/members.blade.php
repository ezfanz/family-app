@extends('layouts.page')

@section('title', 'Group · '.$trip->name)
@section('back', route('trips.show', $trip))
@section('back-label', 'Itinerary')

@section('content')
@php($me = auth()->user())
@php($isOwner = $me->can('manageMembers', $trip))
@php($chevron = '<svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>')

<h1>Group</h1>
<p class="lede tnum">{{ $trip->name }} · {{ $members->count() }} {{ Str::plural('person', $members->count()) }}</p>

@if ($isOwner)
  <section class="code-card" aria-labelledby="code-h"
    data-join-code="{{ $trip->join_code }}" data-trip-name="{{ $trip->name }}" data-join-url="{{ route('trips.index') }}">
    <div class="top">
      <h2 id="code-h">Join code</h2>
      <div class="code tnum" aria-label="Join code {{ implode(' ', str_split($trip->join_code)) }}">{{ substr($trip->join_code, 0, 4) }} {{ substr($trip->join_code, 4) }}</div>
      <p>New people join as Viewer. Change their role below.</p>
      <p class="muted" data-copy-status role="status" aria-live="polite"></p>
    </div>
    <div class="btns">
      <button type="button" data-copy><svg class="i" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a1 1 0 0 1 1-1h9"/></svg>Copy code</button>
      <button type="button" data-share><svg class="i" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 8l5-5 5 5"/><path d="M5 13v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6"/></svg>Share</button>
    </div>
  </section>
  <form method="POST" action="{{ route('trips.join-code.regenerate', $trip) }}">
    @csrf
    <button type="submit" class="underline-btn">Make a new code</button>
  </form>
@endif

<h2 class="sec" style="margin-top:24px">Members</h2>
<ul class="list">
  @foreach ($members as $member)
    @php($isMe = $member->is($me))
    <li class="member">
      <span class="avatar" aria-hidden="true">{{ Str::upper(Str::substr($member->name, 0, 2)) }}</span>
      <span class="who">{{ $member->name }}@if ($isMe)<small>You</small>@endif
        @if ($isOwner && ! $isMe)<span class="email">{{ $member->email }}</span>@endif
      </span>
      @if ($isMe || ! $isOwner || $member->pivot->role === \App\Enums\TripRole::Owner)
        <span class="role-text">{{ $member->pivot->role->value }}</span>
      @else
        <form method="POST" action="{{ route('trips.members.update', [$trip, $member]) }}">
          @csrf
          @method('PATCH')
          <div class="select-wrap">
            <label class="sr-only" for="role-{{ $member->id }}">Role for {{ $member->name }}</label>
            <select id="role-{{ $member->id }}" name="role" data-autosubmit>
              @foreach ($roles as $role)
                <option value="{{ $role->value }}" @selected($member->pivot->role === $role)>{{ ucfirst($role->value) }}</option>
              @endforeach
            </select>
            {!! $chevron !!}
          </div>
          <button type="submit" class="pill-btn" data-autosubmit-button>Save</button>
        </form>
        <form method="POST" action="{{ route('trips.members.destroy', [$trip, $member]) }}" onsubmit="return confirm('Remove {{ e(addslashes($member->name)) }} from the trip?')">
          @csrf
          @method('DELETE')
          <button type="submit" class="icon-btn" aria-label="Remove {{ $member->name }}"><svg class="i" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
        </form>
      @endif
    </li>
  @endforeach
</ul>

<dl class="roles-legend">
  <dt>Owner</dt><dd>Everything, plus roles and the join code.</dd>
  <dt>Editor</dt><dd>Add, change and delete stops and items.</dd>
  <dt>Viewer</dt><dd>Sees the plan, ticks stops, keeps own notes.</dd>
</dl>

<div class="acct-card" style="margin-top:24px">
  <a class="acct-row link" href="{{ route('account') }}">
    <span class="avatar solid small" aria-hidden="true">{{ Str::upper(Str::substr($me->name, 0, 2)) }}</span>
    <span class="acct-who"><strong>Account and log out</strong></span>
    <svg class="i" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" style="color:var(--stone)"><path d="M9 5l7 7-7 7"/></svg>
  </a>
</div>
@endsection

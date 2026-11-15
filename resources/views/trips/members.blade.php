@extends('layouts.page')

@section('title', 'People · '.$trip->name)

@section('content')
<p class="alt" style="text-align:left"><a href="{{ route('trips.show', $trip) }}">← Back to the trip</a></p>
<h2 class="sec">People on this trip</h2>
<p class="sub">{{ $trip->name }}</p>

@can('manageMembers', $trip)
  <div class="panel">
    <h3>Join code</h3>
    <p class="join-code" aria-label="Join code {{ implode(' ', str_split($trip->join_code)) }}">{{ $trip->join_code }}</p>
    <p class="hint">Share this with your group. New people join as viewers. Change their role below.</p>
    <form method="POST" action="{{ route('trips.join-code.regenerate', $trip) }}">
      @csrf
      <button type="submit" class="btn small">Make a new code</button>
    </form>
    <p class="hint">The old code stops working straight away. People already in the trip stay in.</p>
  </div>
@endcan

<div class="panel">
  <ul class="rows">
    @foreach ($members as $member)
      @php($isMe = $member->is(auth()->user()))
      <li>
        <div class="who">
          <strong>{{ $member->name }}@if ($isMe) (you)@endif</strong>
          <span>{{ $member->email }}</span>
        </div>
        <div class="acts">
          @if ($isMe || auth()->user()->cannot('manageMembers', $trip))
            <span class="role {{ $member->pivot->role->value }}">{{ $member->pivot->role->value }}</span>
          @else
            <form method="POST" action="{{ route('trips.members.update', [$trip, $member]) }}">
              @csrf
              @method('PATCH')
              <label class="sr-only" for="role-{{ $member->id }}">Role for {{ $member->name }}</label>
              <select id="role-{{ $member->id }}" name="role">
                @foreach ($roles as $role)
                  <option value="{{ $role->value }}" @selected($member->pivot->role === $role)>{{ ucfirst($role->value) }}</option>
                @endforeach
              </select>
              <button type="submit" class="btn small">Save</button>
            </form>
            <form method="POST" action="{{ route('trips.members.destroy', [$trip, $member]) }}">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn small danger" aria-label="Remove {{ $member->name }}">Remove</button>
            </form>
          @endif
        </div>
      </li>
    @endforeach
  </ul>
</div>

<p class="hint">Owners manage people. Editors can change the plan, shopping and ideas. Viewers can only look.</p>
@endsection

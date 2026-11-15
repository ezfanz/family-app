@extends('layouts.page')

@section('title', $trip->name)

@section('content')
{{-- Placeholder until the itinerary UI is ported in phase 4. --}}
<h2 class="sec">{{ $trip->name }}</h2>
<p class="sub">You’re in this trip as {{ $role->value }}.</p>
<p class="alt" style="text-align:left"><a href="{{ route('trips.members.index', $trip) }}">People on this trip</a></p>
@endsection

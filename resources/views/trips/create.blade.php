@extends('layouts.page')

@section('title', 'Start a new trip · E-Itinerary')
@section('back', route('trips.index'))
@section('back-label', 'Trips')

@section('content')
<h1>Start a new trip</h1>
<p class="lede">You’ll be the owner. Share the join code with your group afterwards.</p>

<form class="form" method="POST" action="{{ route('trips.store') }}" data-loading="Creating" novalidate style="margin-top:28px">
  @csrf
  <div class="field">
    <label for="name">Trip name</label>
    <input id="name" name="name" type="text" class="input" maxlength="120" placeholder="e.g. Japan 2027" value="{{ old('name') }}" required autofocus
      @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
    @error('name')<span class="field-error" id="name-error">{{ $message }}</span>@enderror
  </div>
  <div class="grid2">
    <div class="field">
      <label for="start_date">First day</label>
      <input id="start_date" name="start_date" type="date" class="input tnum" value="{{ old('start_date') }}" required
        @error('start_date') aria-invalid="true" aria-describedby="start-error" @enderror>
      @error('start_date')<span class="field-error" id="start-error">{{ $message }}</span>@enderror
    </div>
    <div class="field">
      <label for="end_date">Last day</label>
      <input id="end_date" name="end_date" type="date" class="input tnum" value="{{ old('end_date') }}" required
        @error('end_date') aria-invalid="true" aria-describedby="end-error" @enderror>
      @error('end_date')<span class="field-error" id="end-error">{{ $message }}</span>@enderror
    </div>
  </div>
  <p class="muted" style="margin:-10px 0 0">Up to 60 days. Times use Japan time.</p>
  <button type="submit" class="btn primary">Create trip</button>
</form>
@endsection

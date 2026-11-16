@extends('layouts.page')

@section('title', 'Make an account · E-Itinerary')
@section('aside', 'Trip planner for the group')

@section('content')
<h1>Make an account</h1>
<p class="lede">Then join your group’s trip with the code from the owner.</p>

<form class="form" method="POST" action="{{ route('register') }}" novalidate style="margin-top:28px">
  @csrf
  <div class="field">
    <label for="name">Your name in the group</label>
    <input id="name" name="name" type="text" class="input" autocomplete="name" maxlength="80" value="{{ old('name') }}" required autofocus
      @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
    @error('name')<span class="field-error" id="name-error">{{ $message }}</span>@enderror
  </div>
  <div class="field">
    <label for="email">Email</label>
    <input id="email" name="email" type="email" class="input" autocomplete="email" inputmode="email" value="{{ old('email') }}" required
      @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
    @error('email')<span class="field-error" id="email-error">{{ $message }}</span>@enderror
  </div>
  <div class="field">
    <label for="password">Password<span class="hint">At least 8 characters</span></label>
    <input id="password" name="password" type="password" class="input" autocomplete="new-password" required
      @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
    @error('password')<span class="field-error" id="password-error">{{ $message }}</span>@enderror
  </div>
  <div class="field">
    <label for="password_confirmation">Password again</label>
    <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password" required>
  </div>
  <button type="submit" class="btn primary">Make account</button>
</form>

<p class="center-note">Already have one? <a href="{{ route('login') }}">Log in</a></p>
@endsection

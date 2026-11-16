@extends('layouts.page')

@section('title', 'Log in · E-Itinerary')
@section('aside', 'Trip planner for the group')

@section('content')
<h1>Log in</h1>
<p class="lede">Use the account you made for this trip.</p>

<form class="form" method="POST" action="{{ route('login') }}" novalidate style="margin-top:28px">
  @csrf
  <div class="field">
    <label for="email">Email</label>
    <input id="email" name="email" type="email" class="input" autocomplete="email" inputmode="email" value="{{ old('email') }}" required autofocus
      @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
    @error('email')<span class="field-error" id="email-error">{{ $message }}</span>@enderror
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input id="password" name="password" type="password" class="input" autocomplete="current-password" required
      @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
    @error('password')<span class="field-error" id="password-error">{{ $message }}</span>@enderror
  </div>
  <label class="check-row" for="remember"><input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember'))><span><strong>Keep me logged in</strong><small>On this device only</small></span></label>
  <button type="submit" class="btn primary">Log in</button>
</form>

<p class="center-note">New here? <a href="{{ route('register') }}">Make an account</a></p>
@endsection

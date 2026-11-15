@extends('layouts.page')

@section('title', 'Make an account · Japan Trip')

@section('content')
<h2 class="sec">Make an account</h2>
<p class="sub">After this, join the trip with the code from the organiser.</p>

<div class="panel">
  <form class="form" method="POST" action="{{ route('register') }}" novalidate>
    @csrf
    <label>Your name
      <input name="name" type="text" autocomplete="name" maxlength="80" value="{{ old('name') }}" required autofocus
        @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
    </label>
    @error('name')<span class="field-error" id="name-error">{{ $message }}</span>@enderror

    <label>Email
      <input name="email" type="email" autocomplete="email" inputmode="email" value="{{ old('email') }}" required
        @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
    </label>
    @error('email')<span class="field-error" id="email-error">{{ $message }}</span>@enderror

    <label>Password <span class="hint">At least 8 characters</span>
      <input name="password" type="password" autocomplete="new-password" required
        @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
    </label>
    @error('password')<span class="field-error" id="password-error">{{ $message }}</span>@enderror

    <label>Password again
      <input name="password_confirmation" type="password" autocomplete="new-password" required>
    </label>

    <button type="submit" class="btn primary">Make account</button>
  </form>
</div>

<p class="alt">Already have one? <a href="{{ route('login') }}">Log in</a></p>
@endsection

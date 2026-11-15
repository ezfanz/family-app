@extends('layouts.page')

@section('title', 'Log in · Japan Trip')

@section('content')
<h2 class="sec">Log in</h2>
<p class="sub">Use the account you made for this trip.</p>

<div class="panel">
  <form class="form" method="POST" action="{{ route('login') }}" novalidate>
    @csrf
    <label>Email
      <input name="email" type="email" autocomplete="email" inputmode="email" value="{{ old('email') }}" required autofocus
        @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
    </label>
    @error('email')<span class="field-error" id="email-error">{{ $message }}</span>@enderror

    <label>Password
      <input name="password" type="password" autocomplete="current-password" required
        @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
    </label>
    @error('password')<span class="field-error" id="password-error">{{ $message }}</span>@enderror

    <label class="inline"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Keep me logged in</label>

    <button type="submit" class="btn primary">Log in</button>
  </form>
</div>

<p class="alt">New here? <a href="{{ route('register') }}">Make an account</a></p>
@endsection

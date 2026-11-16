<!DOCTYPE html>
@php($theme = in_array(request()->cookie('theme'), ['light', 'dark'], true) ? request()->cookie('theme') : null)
<html lang="en"@if ($theme) data-theme="{{ $theme }}"@endif>
<head>
<script>
  {{-- Apply the saved theme before first paint (also covers pages served offline from cache). --}}
  try { var t = localStorage.getItem('theme'); if (t === 'light' || t === 'dark') { document.documentElement.dataset.theme = t; } else if (t === 'system') { document.documentElement.removeAttribute('data-theme'); } } catch (e) {}
</script>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#2A3B8F">
<meta name="format-detection" content="telephone=no">
<link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="E-Itinerary">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<title>@yield('title', 'E-Itinerary')</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700;900&display=swap" rel="stylesheet">
@vite($assets ?? ['resources/css/app.css', 'resources/js/app.js'])
@stack('head')
</head>
<body>
@yield('body')

{{-- Full-screen "Loading" fill text (motion.dev style), shown while the server answers. --}}
<div class="fill-loader hidden" id="loader" role="status" aria-live="polite" aria-label="Loading…">
  <div class="fill-text" aria-hidden="true"><span class="bg">Loading</span><span class="fg">Loading</span></div>
</div>
</body>
</html>

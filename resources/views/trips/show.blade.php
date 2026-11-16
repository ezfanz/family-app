@extends('layouts.app', ['assets' => ['resources/css/app.css', 'resources/js/app.js']])

@section('title', $trip->name)

@section('body')
<div class="shell" id="app"
  data-trip-id="{{ $trip->id }}"
  data-trip-name="{{ $trip->name }}"
  data-api-base="{{ url('/api') }}"
  data-group-url="{{ route('trips.members.index', $trip) }}"
  data-user-id="{{ auth()->id() }}"
  data-flash="{{ session('status') }}">

  <header class="app-header">
    <div class="row">
      <img class="logo" src="/icons/icon.svg" alt="" width="32" height="32">
      <div class="titles">
        <p class="title" id="tripTitle">{{ $trip->name }}</p>
        <div class="subtitle tnum" id="tripSub">{{ $trip->start_date->format('j M') }} – {{ $trip->end_date->format('j M') }}</div>
      </div>
      <a class="sync" id="sync" href="{{ route('trips.members.index', $trip) }}"><span class="dot" aria-hidden="true"></span><span class="txt">Loading…</span></a>
    </div>
    <div class="progress" role="progressbar" aria-label="Trip progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="tripProgress"><div id="tripBar"></div></div>
  </header>

  <div class="offline hidden" id="offline" aria-live="polite">
    <svg class="i" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" style="margin-top:1px"><path d="M3 3l18 18"/><path d="M8.5 8.6A5 5 0 0 0 6.5 18H17"/><path d="M10.6 6.2A5 5 0 0 1 17.6 10 3.5 3.5 0 0 1 20 16"/></svg>
    <div><strong>You’re offline.</strong><span>Changes will sync when you’re back online.</span></div>
  </div>

  <main>
    {{-- ---------- Itinerary ---------- --}}
    <section id="v-plan" aria-labelledby="dayTitle">
      <div class="days-wrap">
        <button type="button" class="days-nav prev" id="daysPrev" aria-label="Scroll days left"><svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></button>
        <nav class="days" id="days" aria-label="Days"></nav>
        <button type="button" class="days-nav next" id="daysNext" aria-label="Scroll days right"><svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg></button>
      </div>
      <div class="dayhead">
        <div style="min-width:0">
          <h1 id="dayTitle">Loading…</h1>
          <p class="meta tnum" id="dayMeta"></p>
        </div>
        <div class="actions">
          <button type="button" class="pill-btn round" id="refreshBtn" aria-label="Refresh trip" title="Get the latest changes"><svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 5v6h-6"/></svg></button>
          <button type="button" class="pill-btn hidden" id="todayBtn">Today</button>
          <button type="button" class="pill-btn hidden" id="addAct"><svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Add<span class="wide-only">&nbsp;stop</span></button>
        </div>
      </div>
      <div id="banner"></div>
      <ol class="tl" id="rail" aria-label="Stops"></ol>
      <div class="empty-day hidden" id="emptyDay">
        <div></div>
        <div class="track" aria-hidden="true"><span class="o"></span><span class="dash-line"></span></div>
        <div>
          <h2>Nothing planned yet.</h2>
          <p id="emptyText">Add a stop, or pick one from your ideas.</p>
          <div class="btns">
            <button type="button" class="btn primary compact hidden" id="emptyAdd"><svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Add a stop</button>
            <a class="btn compact" href="#ideas">Browse ideas</a>
          </div>
        </div>
      </div>
      <div class="add-dashed-wrap hidden" id="addBottom">
        <button type="button" class="add-dashed" id="addActBottom"><svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Add a stop</button>
      </div>
    </section>

    {{-- ---------- Shopping ---------- --}}
    <section id="v-shopping" class="page hidden" aria-labelledby="shopTitle">
      <h1 id="shopTitle">Shopping</h1>
      <p class="lede">Prices are estimates before tax. Type the real price when you buy.</p>
      <div class="grid2" style="margin-top:20px">
        <div><label class="lbl" for="rate">RM per ¥100</label><input id="rate" class="big-input tnum" inputmode="decimal" type="number" step="0.01" min="0"></div>
        <div><label class="lbl" for="deposit">She banked in (RM)</label><input id="deposit" class="big-input tnum" inputmode="decimal" type="number" step="1" min="0" placeholder="0"></div>
      </div>
      <div class="totals" aria-live="polite">
        <div class="cells">
          <div><div class="muted">Estimated</div><div class="v tnum" id="sEst"></div></div>
          <div><div class="muted">Spent</div><div class="v tnum" id="sSpent"></div></div>
          <div><div class="muted">Balance</div><div class="v tnum" id="sBal"></div></div>
        </div>
        <div class="note" id="sNote"></div>
      </div>
      <div class="row-between" style="margin-top:28px">
        <h2 class="sec" style="margin:0">List</h2>
        <span class="muted tnum" id="shopCount"></span>
      </div>
      <ul class="list" id="shopList"></ul>
      <button type="button" class="add-row hidden" id="addItem"><svg class="i" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Add an item</button>
      <p class="muted" style="margin-top:12px">Bought items without a price count at their estimate.</p>
    </section>

    {{-- ---------- Ideas ---------- --}}
    <section id="v-ideas" class="page hidden" aria-labelledby="ideasTitle">
      <div class="page-head">
        <div><h1 id="ideasTitle">Ideas</h1><p class="lede">Places to go if there’s time</p></div>
        <button type="button" class="pill-btn hidden" id="addIdea"><svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Add idea</button>
      </div>
      <div id="ideas"></div>
    </section>
  </main>

  <nav class="tabs" aria-label="Main"><div class="in">
    <a href="#plan" data-v="plan"><svg class="i" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3v18"/><circle cx="8" cy="7" r="2.2"/><circle cx="8" cy="17" r="2.2"/><path d="M13 7h7M13 17h7"/></svg>Itinerary</a>
    <a href="#shopping" data-v="shopping"><svg class="i" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8h14l-1 12H6L5 8z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>Shopping</a>
    <a href="#ideas" data-v="ideas"><svg class="i" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 4h12v16l-6-4-6 4z"/></svg>Ideas</a>
  </div></nav>

  <div class="toast hidden" id="toast" role="status" aria-live="polite"></div>

  <div class="fill-loader hidden" id="loader" role="status" aria-live="polite" aria-label="Loading…">
    <div class="fill-text" aria-hidden="true"><span class="bg">Loading</span><span class="fg">Loading</span></div>
  </div>

  @include('trips.partials.sheets')
</div>
@endsection

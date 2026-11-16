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
      <a class="me" href="{{ route('account') }}" aria-label="Account and settings for {{ auth()->user()->name }}" title="Account">
        <span class="avatar solid" aria-hidden="true">{{ Str::upper(Str::substr(auth()->user()->name, 0, 2)) }}</span>
      </a>
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

    {{-- ---------- Shopping (personal lists; the owner sees everyone) ---------- --}}
    <section id="v-shopping" class="page hidden" aria-labelledby="shopTitle">
      <h1 id="shopTitle">Shopping</h1>
      <p class="lede">Barang kirim for family back home</p>

      {{-- Yen to Ringgit converter (collapsed by default), rate from Bank Negara Malaysia --}}
      <details class="fold fx-card" id="fxFold">
        <summary class="fold-sum">
          <svg class="flag-jp" viewBox="0 0 30 20" width="30" height="20" role="img" aria-label="Japan flag"><rect width="30" height="20" fill="#FFFFFF"/><circle cx="15" cy="10" r="6" fill="#BC002D"/></svg>
          <span class="fold-title"><strong>Yen to Ringgit</strong><span class="muted tnum" id="fxSummary">Today’s rate</span></span>
          <svg class="i fold-chev" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
        </summary>
        <div class="fold-body">
          <div class="fx-head">
            <p class="muted fx-src" id="fxSource" aria-live="polite">Getting today’s rate…</p>
            <button type="button" class="icon-btn fx-refresh" id="fxRefresh" aria-label="Update rate" title="Update rate"><svg class="i" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 5v6h-6"/></svg></button>
          </div>
          <label class="fx-row" for="fxYen">
            <span class="fx-sym" aria-hidden="true">¥</span>
            <input id="fxYen" class="tnum" inputmode="decimal" autocomplete="off" value="1000" aria-label="Amount in Japanese yen">
            <span class="fx-code">JPY</span>
          </label>
          <label class="fx-row" for="fxRm">
            <span class="fx-sym" aria-hidden="true">RM</span>
            <input id="fxRm" class="tnum" inputmode="decimal" autocomplete="off" aria-label="Amount in Malaysian ringgit">
            <span class="fx-code">MYR</span>
          </label>
          <p class="fx-rate tnum" id="fxRateLine"></p>
          <div class="fx-compare hidden" id="fxCompare">
            <span id="fxCompareText"></span>
            <button type="button" class="pill-btn fx-use" id="fxUse">Use today’s rate</button>
          </div>
        </div>
      </details>

      {{-- Owner only: whose list to show (collapsed by default) --}}
      <details class="fold admin-fold hidden" id="shopAdminNote">
        <summary class="fold-sum">
          <svg class="i fold-ico" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/></svg>
          <span class="fold-title"><strong>Admin view</strong><span class="muted" id="shopViewing">Showing your list</span></span>
          <svg class="i fold-chev" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
        </summary>
        <div class="fold-body">
          <p class="muted" style="margin:0">You can see everyone’s spending. Pick whose list to show.</p>
          <nav class="chips" id="shopChips" aria-label="Whose shopping"></nav>
        </div>
      </details>

      {{-- Everyone (owner only) --}}
      <div id="shopAll" class="hidden">
        <div class="totals" id="allTotals" aria-live="polite"></div>
        <label class="rate-row" for="rateAll">Rate for everyone, RM per ¥100
          <input id="rateAll" class="rate-input tnum" inputmode="decimal" type="number" step="0.0001" min="0" data-rate>
        </label>
        @include('trips.partials.auto-rate', ['suffix' => 'All'])
        <h2 class="sec" style="margin:28px 0 10px">By person</h2>
        <div class="people" id="shopPeople"></div>
      </div>

      {{-- One person --}}
      <div id="shopPerson" class="hidden">
        <div class="person-head" id="personHead"></div>
        <div class="grid2" style="margin-top:16px">
          <div><label class="lbl" for="budget">Budget (RM)</label><input id="budget" class="big-input tnum" inputmode="decimal" type="number" step="1" min="0" placeholder="0"></div>
          <div><label class="lbl" for="rate">RM per ¥100</label><input id="rate" class="big-input tnum" inputmode="decimal" type="number" step="0.0001" min="0" data-rate></div>
        </div>
        @include('trips.partials.auto-rate', ['suffix' => 'Person'])
        <div class="totals" id="personTotals" aria-live="polite"></div>
        <p class="muted tnum" id="personLeft" style="margin:10px 2px 0"></p>
        <div class="row-between" style="margin-top:24px">
          <h2 class="sec" style="margin:0">List</h2>
          <span class="muted tnum" id="shopCount"></span>
        </div>
        <nav class="chips for-chips hidden" id="forChips" aria-label="Filter by who it is for"></nav>
        <ul class="list" id="shopList"></ul>
        <button type="button" class="add-row" id="addItem"><svg class="i" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Add an item</button>
      </div>

      {{-- Import an Excel shopping list (E-Itinerary template) --}}
      <section class="import-card" aria-labelledby="importTitle">
        <div class="ic-head">
          <span class="ic-icon" aria-hidden="true">
            <svg class="i" width="24" height="24" viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 12l6 6M15 12l-6 6"/></svg>
          </span>
          <div>
            <h2 id="importTitle">Import from Excel</h2>
            <p>Fill in the E-Itinerary template, then upload it. New rows are added and matching rows are updated. Nothing is deleted.</p>
          </div>
        </div>
        <label class="btn primary file-btn" for="importFile">
          <svg class="i" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15V3M7 8l5-5 5 5"/><path d="M5 15v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4"/></svg>
          Choose Excel file
        </label>
        <input type="file" id="importFile" class="file-input" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
        <a class="btn" href="/templates/e-itinerary-shopping-list.xlsx" download="e-itinerary-shopping-list.xlsx" data-no-loader>
          <svg class="i" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5"/><path d="M5 15v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4"/></svg>
          Download template
        </a>
        <p class="muted ic-foot">Excel .xlsx only · up to 2 MB and 500 items · needs internet</p>
      </section>
    </section>

    {{-- ---------- Ideas (shared with everyone in the trip) ---------- --}}
    <section id="v-ideas" class="page hidden" aria-labelledby="ideasTitle">
      <div class="page-head">
        <div><h1 id="ideasTitle">Ideas</h1><p class="lede">Places to go if there’s time</p></div>
        <button type="button" class="pill-btn" id="addIdea"><svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Add idea</button>
      </div>
      <div class="info-note">
        <svg class="i" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 4.5a3 3 0 0 1 0 6M18 14c2 .7 3 2.8 3 5"/></svg>
        <span><b>Shared with everyone in the trip.</b> Anyone can add, tick, plan or delete an idea.</span>
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


  @include('trips.partials.sheets')
</div>
@endsection

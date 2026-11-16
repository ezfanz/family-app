{{-- Shopping → Overview: spending dashboard. Computed on the phone from the trip data (overviewcalc.js). --}}
<div class="overview-only" id="overviewPane">
  <nav class="ov-chips hidden" id="ovChips" aria-label="Show spending for"></nav>

  <section class="ov-card ov-hero" aria-label="Spending">
    <div class="ov-label" id="ovHeroLabel">Spent</div>
    <div class="ov-big tnum" id="ovSpent">RM0.00</div>
    <div class="ov-sub tnum" id="ovBudgetLine"></div>
    <div class="ov-meter" role="meter" aria-label="Budget used" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="ovMeter"><div id="ovMeterBar"></div></div>
    <div class="ov-meter-row tnum"><span id="ovUsed">0% used</span><span id="ovLeft"></span></div>
  </section>

  <div class="ov-tiles">
    <button type="button" class="ov-card ov-tile" data-ov-go="list">
      <span class="ov-label">Items to buy</span>
      <span class="ov-tile-num tnum" id="ovToBuy">0</span>
      <span class="ov-sub tnum" id="ovBoughtLine"></span>
      <span class="ov-bar" aria-hidden="true"><span id="ovItemsBar"></span></span>
    </button>
    <button type="button" class="ov-card ov-tile" data-ov-go="receipts">
      <span class="ov-label">Receipts captured</span>
      <span class="ov-tile-num tnum" id="ovReceipts">0</span>
      <span class="ov-sub tnum" id="ovEligibleLine"></span>
      <span class="ov-bar ok" aria-hidden="true"><span id="ovReceiptsBar"></span></span>
    </button>
  </div>

  <button type="button" class="ov-card ov-refund" data-ov-go="receipts">
    <span class="ov-refund-icon" aria-hidden="true"><svg class="i" width="22" height="22" viewBox="0 0 24 24"><path d="M6 3h12v18l-2-1.5-2 1.5-2-1.5-2 1.5-2-1.5L6 21z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg></span>
    <span class="ov-refund-text">
      <span class="ov-label">Expected tax refund</span>
      <span class="ov-refund-num tnum"><span id="ovRefundYen">¥0</span> <span class="ov-refund-rm" id="ovRefundRm"></span></span>
      <span class="ov-sub" id="ovRefundLine"></span>
    </span>
    <svg class="i ov-chev" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
  </button>

  <section class="ov-card ov-section" aria-labelledby="ovDayTitle">
    <div class="ov-head"><h2 id="ovDayTitle">Spent by day</h2><span class="ov-unit" id="ovUnitDay"></span></div>
    <div class="ov-sel tnum" aria-live="polite"><div class="ov-sel-value" id="ovSelValue"></div><div class="ov-sub" id="ovSelLine"></div></div>
    <div class="ov-chart" id="ovChart">
      <div class="ov-grid" id="ovGrid" aria-hidden="true"></div>
      <div class="ov-cols" id="ovCols"></div>
    </div>
    <div class="ov-nums" id="ovNums" aria-hidden="true"></div>
    <div class="ov-legend"><span><span class="ov-key" aria-hidden="true"></span>Spent</span><span><span class="ov-key ahead" aria-hidden="true"></span>Days ahead</span></div>
    <p class="ov-sub ov-before tnum hidden" id="ovBefore"></p>
  </section>

  <section class="ov-card ov-section" aria-labelledby="ovCatTitle">
    <div class="ov-head"><h2 id="ovCatTitle">Spent by category</h2><span class="ov-unit" id="ovUnitCat"></span></div>
    <ul class="ov-cats" id="ovCats"></ul>
    <p class="ov-sub hidden" id="ovNoCats" style="margin:8px 0 0">Nothing bought yet.</p>
  </section>

  <section class="ov-card ov-section ov-people-sec hidden" id="ovPeopleSec" aria-labelledby="ovPplTitle">
    <div class="ov-head"><h2 id="ovPplTitle">Budget by person</h2><span class="ov-unit">Tap to filter</span></div>
    <ul class="ov-people" id="ovPeople"></ul>
  </section>
</div>

{{-- Shopping → Receipts: tax refund tracking. Only shown on the Receipts tab. --}}
<div class="receipts-only" id="receiptsPane">
  {{-- Owner on "Everyone": the group's refunds and one card per person --}}
  <div id="rcAdmin" class="hidden">
    <p class="rc-admin-note"><svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 3v6c0 4.5-3.4 8-8 9-4.6-1-8-4.5-8-9V6z"/></svg><span><b>Others’ receipts.</b> As an owner you can see everyone’s receipts.</span></p>
    <section class="rc-summary" aria-label="Group refund summary">
      <div class="rc-sum-top">
        <div class="rc-label">Group expected refund</div>
        <div class="rc-big"><span class="tnum" id="rcGroupExpected">¥0</span><span class="tnum rc-big-rm" id="rcGroupExpectedRm"></span></div>
        <div class="rc-bar" aria-hidden="true"><span class="got" id="rcGroupBarReceived"></span><span class="wait" id="rcGroupBarWaiting"></span></div>
      </div>
      <div class="rc-sum-grid two">
        <div><div class="rc-label"><span class="rc-key got" aria-hidden="true"></span>Received</div><div class="tnum rc-num"><span id="rcGroupReceived">¥0</span> <small id="rcGroupReceivedRm"></small></div></div>
        <div><div class="rc-label"><span class="rc-key wait" aria-hidden="true"></span>Waiting</div><div class="tnum rc-num"><span id="rcGroupWaiting">¥0</span> <small id="rcGroupWaitingRm"></small></div></div>
      </div>
    </section>
    <h2 class="sec" style="margin:26px 0 10px">By person</h2>
    <div class="rc-people" id="rcPeople"></div>
  </div>

  <div id="rcMine">
  <section class="rc-summary" aria-label="Refund summary">
    <div class="rc-sum-top">
      <div class="rc-label">Expected tax refund</div>
      <div class="rc-big"><span class="tnum" id="rcExpected">¥0</span><span class="tnum rc-big-rm" id="rcExpectedRm"></span></div>
      <div class="rc-bar" aria-hidden="true"><span class="got" id="rcBarReceived"></span><span class="wait" id="rcBarWaiting"></span></div>
    </div>
    <div class="rc-sum-grid">
      <div><div class="rc-label"><span class="rc-key got" aria-hidden="true"></span>Received</div><div class="tnum rc-num" id="rcReceived">¥0</div></div>
      <div><div class="rc-label"><span class="rc-key wait" aria-hidden="true"></span>Waiting</div><div class="tnum rc-num" id="rcWaiting">¥0</div></div>
      <div><div class="rc-label"><span class="rc-key" aria-hidden="true"></span>To confirm</div><div class="tnum rc-num" id="rcToConfirm">¥0</div></div>
    </div>
  </section>

  <div class="rc-actions" id="rcActions">
    <button type="button" class="btn primary rc-snap" id="rcSnap"><svg class="i" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>Snap receipt</button>
    <button type="button" class="btn secondary rc-airport" id="rcAirport"><svg class="i" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 16l20-6-2-2-7 2-5-6-2 1 3 6-5 2-2-2-1 1z"/><path d="M3 21h18"/></svg>Airport</button>
  </div>

  <nav class="chips rc-filters" id="rcFilters" aria-label="Filter receipts"></nav>

  <div id="rcGroups"></div>
  <p class="rc-empty hidden" id="rcEmpty">No receipts here.</p>
  <div class="rc-none hidden" id="rcNone">
    <h2>No receipts yet</h2>
    <p>After you pay at a tax-free shop, snap the receipt. Spend ¥5,000 or more before tax at one store in one day to qualify.</p>
  </div>
  </div>

  <p class="rc-note"><svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg><span>Refunds are paid by each store after customs confirms you took the goods out of Japan. Amounts are expected, not guaranteed. They don’t change your shopping balance.</span></p>
</div>

@php($close = '<svg class="i" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>')

{{-- ---------- Add / edit a stop ---------- --}}
<dialog class="sheet" id="actDlg" aria-labelledby="actDlgTitle">
  <form id="actForm" novalidate>
    <div class="grab" aria-hidden="true"></div>
    <div class="sheet-head">
      <h2 id="actDlgTitle">Add a stop</h2>
      <button type="button" class="icon-btn" data-close aria-label="Close">{!! $close !!}</button>
    </div>
    <div class="sheet-body form">
      <div class="split">
        <div class="field"><label for="f-day">Day</label><select id="f-day" name="day" class="input"></select></div>
        <div class="field"><label for="f-time">Time</label><input id="f-time" name="time" type="time" class="input tnum" style="font-weight:700" required></div>
      </div>
      <div class="field"><label for="f-name">Name</label><input id="f-name" name="name" type="text" class="input" maxlength="160" placeholder="e.g. Osaka Castle" required></div>
      <div class="field"><label for="f-places">Places<span class="hint">One per line. Each becomes a Google Maps link.</span></label><textarea id="f-places" name="places" rows="3" class="input"></textarea></div>
      <div class="field"><label for="f-transport">Getting there</label><textarea id="f-transport" name="transport" rows="2" class="input" maxlength="200" placeholder="e.g. Midosuji Line → Umeda"></textarea></div>
      <fieldset class="plain field"><legend>Meal</legend>
        <div class="pills">
          <label class="pill"><input type="radio" name="meal" value="" checked><span>None</span></label>
          <label class="pill"><input type="radio" name="meal" value="Breakfast"><span>Breakfast</span></label>
          <label class="pill"><input type="radio" name="meal" value="Lunch"><span>Lunch</span></label>
          <label class="pill"><input type="radio" name="meal" value="Dinner"><span>Dinner</span></label>
          <label class="pill"><input type="radio" name="meal" value="Snack"><span>Snack</span></label>
        </div>
      </fieldset>
      <div class="field"><label for="f-ticket">Ticket<span class="hint">Where it’s booked, e.g. Klook. Shows an amber tag.</span></label><input id="f-ticket" name="ticket" type="text" class="input" maxlength="40"></div>
      <label class="check-row" for="f-halal"><input id="f-halal" name="halal" type="checkbox"><span><strong>Halal</strong><small>Shows a green Halal tag</small></span></label>
      <div class="field"><label for="f-notes">Notes<span class="hint">Everyone in the group can see these.</span></label><textarea id="f-notes" name="notes" rows="3" class="input" maxlength="1000" placeholder="Opening hours, what to order, meeting point"></textarea></div>
      <div class="danger-row"><button type="button" class="btn danger compact" id="actDel">Delete this stop</button></div>
    </div>
    <div class="sheet-foot">
      <button type="button" class="btn secondary" data-close>Cancel</button>
      <button type="submit" class="btn primary">Save stop</button>
    </div>
  </form>
</dialog>

{{-- ---------- Add / edit a shopping item ---------- --}}
<dialog class="sheet" id="itemDlg" aria-labelledby="itemDlgTitle">
  <form id="itemForm" novalidate>
    <div class="grab" aria-hidden="true"></div>
    <div class="sheet-head">
      <h2 id="itemDlgTitle">Add an item</h2>
      <button type="button" class="icon-btn" data-close aria-label="Close">{!! $close !!}</button>
    </div>
    <div class="sheet-body form">
      <div class="field"><label for="i-name">Item</label><input id="i-name" name="name" type="text" class="input" maxlength="160" placeholder="e.g. Tokyo Banana, 8 pcs" required></div>
      <div class="web-pills hidden" id="itemWeb">
        <a class="menu-pill" id="itemGoogle" target="_blank" rel="noopener"><svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/></svg>Search Google</a>
        <a class="menu-pill" id="itemImages" target="_blank" rel="noopener"><svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 17l-5-5-9 8"/></svg>Google Images</a>
      </div>
      <div class="grid2">
        <div class="field"><label for="i-store">Store</label><input id="i-store" name="store" type="text" class="input" maxlength="60" placeholder="e.g. MUJI" list="storeList"><datalist id="storeList"></datalist></div>
        <div class="field"><label for="i-for">For who</label><input id="i-for" name="for_whom" type="text" class="input" maxlength="80" placeholder="e.g. Mak" list="forList"><datalist id="forList"></datalist></div>
      </div>
      <div class="field"><label for="i-details">Details<span class="hint">Colour, size or code</span></label><input id="i-details" name="details" type="text" class="input" maxlength="160"></div>
      <div class="grid2">
        <div class="field"><label for="i-qty">Quantity</label><input id="i-qty" name="qty" type="number" min="1" max="999" step="1" inputmode="numeric" class="input tnum"></div>
        <div class="field"><label for="i-est">Estimated total ¥</label><input id="i-est" name="est" type="number" min="0" step="10" inputmode="numeric" class="input tnum"></div>
      </div>
      <p class="muted" id="itemRm" style="margin:-10px 0 0"></p>
      <div class="field"><label for="i-link">Link<span class="hint">Product page or photo</span></label><input id="i-link" name="link" type="url" inputmode="url" class="input" maxlength="500" placeholder="https://"><a class="text-link hidden" id="itemLinkOpen" target="_blank" rel="noopener">Open link</a></div>
      <div class="field"><label for="i-notes">Notes</label><textarea id="i-notes" name="notes" rows="2" class="input" maxlength="1000"></textarea></div>
      <div class="danger-row"><button type="button" class="btn danger compact" id="itemDel">Delete this item</button></div>
    </div>
    <div class="sheet-foot">
      <button type="button" class="btn secondary" data-close>Cancel</button>
      <button type="submit" class="btn primary">Save item</button>
    </div>
  </form>
</dialog>

{{-- ---------- Add / edit an idea ---------- --}}
<dialog class="sheet" id="ideaDlg" aria-labelledby="ideaDlgTitle">
  <form id="ideaForm" novalidate>
    <div class="grab" aria-hidden="true"></div>
    <div class="sheet-head">
      <h2 id="ideaDlgTitle">Add an idea</h2>
      <button type="button" class="icon-btn" data-close aria-label="Close">{!! $close !!}</button>
    </div>
    <div class="sheet-body form">
      <div class="field"><label for="d-name">Place</label><input id="d-name" name="name" type="text" class="input" maxlength="160" placeholder="e.g. Fushimi Inari Taisha" required></div>
      <div class="field"><label for="d-city">City<span class="hint">Picks the section for you</span></label><select id="d-city" name="city" class="input"><option value="">Not set</option><option>Osaka</option><option>Nara</option><option>Kyoto</option><option>Tokyo</option><option>Fuji</option></select></div>
      <fieldset class="plain field">
        <legend>Section<span class="hint">Where it appears on the Ideas page</span></legend>
        <div class="pills" id="regionPills"></div>
        <input id="d-region-new" name="region_new" type="text" class="input hidden" maxlength="32" placeholder="New section name, e.g. Hokkaido" aria-label="New section name">
      </fieldset>
      <div class="field"><label for="d-area">Area<span class="hint">Optional, e.g. Fushimi</span></label><input id="d-area" name="area" type="text" class="input" maxlength="80"></div>
      <div class="danger-row"><button type="button" class="btn danger compact" id="ideaDel">Delete this idea</button></div>
    </div>
    <div class="sheet-foot">
      <button type="button" class="btn secondary" data-close>Cancel</button>
      <button type="submit" class="btn primary">Save idea</button>
    </div>
  </form>
</dialog>

{{-- ---------- Snap receipt (full-screen camera) ---------- --}}
<dialog class="camera" id="camDlg" aria-labelledby="camTitle">
  <div class="cam-top">
    <button type="button" class="cam-icon" id="camClose" aria-label="Close camera">{!! $close !!}</button>
    <span id="camTitle">Snap receipt</span>
    <button type="button" class="cam-icon" id="camFlash" aria-label="Flash" aria-pressed="false" hidden><svg class="i" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="M13 2L4 14h7l-1 8 9-12h-7z"/></svg></button>
  </div>
  <div class="cam-view" id="camView">
    <video id="camVideo" playsinline muted autoplay></video>
    <div class="cam-frame" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
    <span class="cam-status" id="camStatus" role="status" hidden></span>
    <div class="cam-fallback">
      <p id="camFallbackText">Opening the camera…</p>
      <label class="btn primary cam-fallback-btn" for="camCapture">Take a photo</label>
    </div>
  </div>
  <p class="cam-hint">Fit the whole receipt in the frame. Long receipt? Take it in two parts.</p>
  <div class="cam-controls">
    <button type="button" class="cam-side" id="camPhotos"><svg class="i" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 17l-5-5-9 8"/></svg>Photos</button>
    <button type="button" class="cam-shutter" id="camShutter" aria-label="Take photo" autofocus><span></span></button>
    <button type="button" class="cam-side" id="camTwo" aria-pressed="false"><span class="cam-two">2</span>Two parts</button>
  </div>
  <p class="cam-offline"><svg class="i" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18"/><path d="M8.5 8.6A5 5 0 0 0 6.5 18H17"/></svg>No signal? It saves on this phone and uploads later.</p>
  <input type="file" id="camPick" accept="image/*" multiple hidden>
  <input type="file" id="camCapture" accept="image/*" capture="environment" hidden>
</dialog>

{{-- ---------- Check receipt (after a snap, or tap a receipt) ---------- --}}
<dialog class="sheet tall" id="rvDlg" aria-labelledby="rvTitle">
  <form id="rvForm" novalidate>
    <div class="grab" aria-hidden="true"></div>
    <div class="sheet-head">
      <div><h2 id="rvTitle">Check receipt</h2><p class="muted rv-lede">Fix anything that looks wrong, then save.</p></div>
      <button type="button" class="icon-btn" data-close aria-label="Close">{!! $close !!}</button>
    </div>
    <div class="sheet-body form">
      <div class="rv-photo">
        <span class="rv-thumb" id="rvThumb"></span>
        <div class="rv-photo-text">
          <div class="rv-photo-title" id="rvPhotoTitle">Photo saved</div>
          <div class="muted" id="rvPhotoSub">Kept until the refund arrives</div>
          <div class="rv-photo-actions">
            <button type="button" class="rv-mini" id="rvRetake">Retake</button>
            <button type="button" class="rv-mini" id="rvPart2">Add part 2</button>
          </div>
        </div>
      </div>

      <div class="rv-banner" id="rvBanner" aria-live="polite">
        <span class="rv-banner-icon" id="rvBannerIcon" aria-hidden="true"></span>
        <div><div class="rv-banner-title" id="rvBannerTitle"></div><p class="tnum" id="rvBannerText"></p></div>
      </div>

      <div class="grid2">
        <div class="field" data-field="store_name"><label for="rvStore">Store</label><input id="rvStore" name="store_name" type="text" class="input" maxlength="80" list="rvStoreList" placeholder="e.g. Don Quijote" autocomplete="off" required><datalist id="rvStoreList"></datalist></div>
        <div class="field" data-field="branch"><label for="rvBranch">Branch<span class="hint">Optional</span></label><input id="rvBranch" name="branch" type="text" class="input" maxlength="80" placeholder="e.g. Dotonbori" autocomplete="off"></div>
        <div class="field" data-field="date"><label for="rvDate">Date</label><input id="rvDate" name="date" type="date" class="input tnum" required></div>
        <div class="field" data-field="time"><label for="rvTime">Time<span class="hint">Japan time</span></label><input id="rvTime" name="time" type="time" class="input tnum" required></div>
      </div>

      <fieldset class="plain field">
        <legend>Amount on the receipt</legend>
        <div class="pills">
          <label class="pill"><input type="radio" name="amount_kind" value="pre" checked><span>Before tax</span></label>
          <label class="pill"><input type="radio" name="amount_kind" value="total"><span>Total paid</span></label>
        </div>
        <div class="field rv-amount" data-field="pre_tax_yen">
          <div class="yen-input"><span aria-hidden="true">¥</span><input id="rvAmount" name="amount" type="number" min="1" max="10000000" step="1" inputmode="numeric" class="input tnum rv-amount-input" aria-label="Amount in yen" required></div>
          <p class="muted rv-est hidden" id="rvEstimate"></p>
        </div>
      </fieldset>

      <fieldset class="plain field">
        <legend>Tax</legend>
        <div class="pills">
          <label class="pill"><input type="radio" name="tax_rate" value="10" checked><span>10%</span></label>
          <label class="pill"><input type="radio" name="tax_rate" value="8"><span>8% food and drink</span></label>
        </div>
      </fieldset>

      <fieldset class="plain field hidden" id="rvOwnerSet">
        <legend>Whose receipt</legend>
        <div class="pills" id="rvOwners"></div>
      </fieldset>

      <div class="field">
        <div class="rv-label">Link to shopping list<span class="hint">Optional</span></div>
        <div class="rv-links" id="rvLinks"></div>
        <details class="rv-pick" id="rvPick">
          <summary class="rv-link-add"><svg class="i" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Link item</summary>
          <div class="rv-pick-list" id="rvPickList"></div>
        </details>
        <p class="muted rv-link-note">Linked items are ticked as bought. With one item linked, its Paid ¥ is filled in from this receipt.</p>
      </div>

      <p class="rv-reminder"><svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg><span>Tax-free goods are for personal use and must leave Japan with you, unused.</span></p>

      <div class="danger-row"><button type="button" class="btn danger compact" id="rvDelete">Delete this receipt</button></div>
    </div>
    <div class="sheet-foot rv-foot">
      <div class="rv-expected tnum"><span>Expected refund</span><strong id="rvExpected">¥0</strong></div>
      <button type="submit" class="btn primary">Save receipt</button>
    </div>
  </form>
</dialog>

{{-- ---------- Departure day: tax refund at the airport ---------- --}}
<dialog class="sheet tall" id="airDlg" aria-labelledby="airTitle">
  <div class="grab" aria-hidden="true"></div>
  <div class="sheet-head">
    <div><p class="muted tnum air-day" id="airDay"></p><h2 id="airTitle">Tax refund at the airport</h2></div>
    <button type="button" class="icon-btn" data-close aria-label="Close">{!! $close !!}</button>
  </div>
  <div class="sheet-body">
    <div class="air-alert">
      <svg class="i" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5M9 2h6"/></svg>
      <div><div class="air-alert-title">Get there 3 hours early</div><p>Customs comes first. Skip it and the stores can’t refund the tax.</p></div>
    </div>

    <h3 class="air-h">Before you queue</h3>
    <ul class="air-steps" id="airSteps"></ul>

    <div class="row-between air-h-row">
      <h3 class="air-h" style="margin:0">At customs</h3>
      <span class="muted tnum" id="airProgress"></span>
    </div>
    <div class="air-bar" aria-hidden="true"><div id="airBar"></div></div>
    <p class="muted air-how">Scan your passport at the customs kiosk (or use Visit Japan Web). It confirms all your tax-free purchases at once.</p>
    <ul class="air-list" id="airReceipts"></ul>
    <p class="muted air-none hidden" id="airNone">No eligible receipts yet. Snap receipts under Shopping → Receipts as you shop.</p>

    <p class="rc-note"><svg class="i" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg><span>After customs, each store sends the refund to the card or method you chose when buying. Track it under Receipts.</span></p>
  </div>
  <div class="sheet-foot rv-foot">
    <button type="button" class="btn primary" id="airDone">I’ve done customs</button>
  </div>
</dialog>

{{-- ---------- Mark a tax refund as received ---------- --}}
<dialog class="sheet" id="refundDlg" aria-labelledby="refundDlgTitle">
  <form id="refundForm" novalidate>
    <div class="grab" aria-hidden="true"></div>
    <div class="sheet-head">
      <h2 id="refundDlgTitle">Refund received</h2>
      <button type="button" class="icon-btn" data-close aria-label="Close">{!! $close !!}</button>
    </div>
    <div class="sheet-body form">
      <p class="muted" id="refundHint" style="margin:0"></p>
      <div class="field"><label for="refundAmount">Amount received</label>
        <div class="yen-input"><span aria-hidden="true">¥</span><input id="refundAmount" name="amount" type="number" min="0" max="1000000" step="1" inputmode="numeric" class="input tnum" required></div>
      </div>
      <div class="danger-row"><button type="button" class="btn secondary compact" id="refundNotReceived">It never arrived</button></div>
    </div>
    <div class="sheet-foot">
      <button type="button" class="btn secondary" data-close>Cancel</button>
      <button type="submit" class="btn primary">Mark refunded</button>
    </div>
  </form>
</dialog>

{{-- ---------- Review an Excel import before saving ---------- --}}
<dialog class="sheet" id="importDlg" aria-labelledby="importDlgTitle">
  <div class="grab" aria-hidden="true"></div>
  <div class="sheet-head">
    <h2 id="importDlgTitle">Check your list</h2>
    <button type="button" class="icon-btn" data-close aria-label="Close">{!! $close !!}</button>
  </div>
  <div class="sheet-body">
    <p class="import-file" id="importFileName"></p>
    <div class="import-summary" id="importSummary"></div>
    <div id="importProblems"></div>
    <div id="importRows"></div>
  </div>
  <div class="sheet-foot">
    <button type="button" class="btn secondary" data-close>Cancel</button>
    <button type="button" class="btn primary" id="importConfirm" autofocus>Import</button>
  </div>
</dialog>

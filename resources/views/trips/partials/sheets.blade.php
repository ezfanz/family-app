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

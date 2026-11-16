{{-- Switch: use today's Bank Negara rate automatically (default) or the trip's own rate. --}}
<label class="switch-row auto-rate" for="autoRate{{ $suffix }}">
  <span class="acct-who">
    <strong>Today’s rate automatically</strong>
    <span data-auto-rate-note>Bank Negara Malaysia, updated daily</span>
  </span>
  <span class="switch">
    <input type="checkbox" id="autoRate{{ $suffix }}" role="switch" data-rate-auto checked>
    <span class="track" aria-hidden="true"><span class="thumb"></span></span>
  </span>
</label>

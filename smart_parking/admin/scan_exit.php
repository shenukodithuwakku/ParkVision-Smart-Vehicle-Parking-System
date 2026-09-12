<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$admin = require_admin_login();
$pageTitle = 'Scan Exit (QR)';
$activeNav = 'scan_exit';
include __DIR__ . '/includes/header.php';
?>

<style>
/* ─── Scan Exit page — reference image match ─── */
/* Navigation bar: UNTOUCHED */

.se-page { max-width: 1160px; }

/* Page title row */
.se-title-row { display:flex; align-items:center; gap:.85rem; margin-bottom:.45rem; }
.se-title-icon {
  width:44px; height:44px; border-radius:11px;
  background:rgba(37,99,235,.16); border:1px solid rgba(56,130,235,.3);
  display:flex; align-items:center; justify-content:center;
  color:#38bdf8; font-size:1.25rem; flex-shrink:0;
}
.se-title-row h4 { font-size:1.25rem; font-weight:800; color:#e8edf5; margin:0; letter-spacing:-.01em; }
.se-subtitle { font-size:.87rem; color:#7691c0; margin-bottom:1.5rem; max-width:780px; line-height:1.5; }

/* Payment method */
.se-payment-group { margin-bottom:1.5rem; }
.se-payment-group label {
  display:block; font-size:.72rem; font-weight:700;
  text-transform:uppercase; letter-spacing:.08em; color:#7691c0; margin-bottom:.5rem;
}
.se-payment-select-wrap { position:relative; }
.se-payment-select-wrap .pay-icon {
  position:absolute; left:.9rem; top:50%; transform:translateY(-50%);
  color:#8aa0c4; font-size:1rem; pointer-events:none;
}
.se-payment-select-wrap select {
  width:100%; padding:.82rem 1rem .82rem 2.6rem;
  background:rgba(255,255,255,.05); border:1.5px solid rgba(255,255,255,.12);
  border-radius:.75rem; color:#e8edf5; font-size:.95rem; font-family:'Inter',sans-serif;
  appearance:none; outline:none;
  transition:border-color .2s, box-shadow .2s;
}
.se-payment-select-wrap select:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.18); }
.se-payment-select-wrap .caret {
  position:absolute; right:1rem; top:50%; transform:translateY(-50%);
  color:#8aa0c4; pointer-events:none;
}
.se-payment-select-wrap select option { background:#0d1a38; }

/* Card details (shown when payment method = card) */
.se-card-fields {
  margin-top:.85rem; padding:1rem 1.1rem;
  background:rgba(255,255,255,.035); border:1.5px solid rgba(255,255,255,.1);
  border-radius:.85rem; display:none;
}
.se-card-fields.show { display:block; }
.se-card-fields .cf-title {
  font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em;
  color:#7691c0; margin-bottom:.7rem; display:flex; align-items:center; gap:.4rem;
}
.se-card-fields .cf-row { display:flex; gap:.65rem; margin-bottom:.65rem; }
.se-card-fields .cf-row:last-child { margin-bottom:0; }
.se-card-fields .cf-group { flex:1; position:relative; }
.se-card-fields .cf-group.small { flex:0 0 110px; }
.se-card-fields .cf-group i {
  position:absolute; left:.85rem; top:50%; transform:translateY(-50%);
  color:#64748b; font-size:.9rem; pointer-events:none;
}
.se-card-fields input {
  width:100%; padding:.68rem .9rem .68rem 2.4rem;
  background:rgba(255,255,255,.05); border:1.5px solid rgba(255,255,255,.12);
  border-radius:.6rem; color:#e8edf5; font-size:.86rem; outline:none;
  font-family:'Inter',sans-serif; transition:border-color .2s,box-shadow .2s;
}
.se-card-fields input::placeholder { color:#475569; }
.se-card-fields input:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.18); }
.se-card-fields .cf-hint { font-size:.7rem; color:#5a6a88; margin-top:.4rem; }
.se-card-err { color:#f87171; font-size:.76rem; margin-top:.5rem; display:none; }

/* ─── Main grid ─── */
.se-main-grid {
  display:grid;
  grid-template-columns: 560px 1fr;
  gap: 1.4rem;
  align-items: start;
  margin-bottom: 1.4rem;
}
@media(max-width:1000px){ .se-main-grid{ grid-template-columns:1fr; } }

/* Camera card */
.se-cam-card {
  background:rgba(8,16,36,.95);
  border:1.5px solid rgba(56,130,235,.22);
  border-radius:1rem; overflow:hidden;
}
.se-cam-topbar {
  display:flex; align-items:center; justify-content:space-between;
  padding:.7rem 1.1rem; border-bottom:1px solid rgba(255,255,255,.07);
}
.se-live-badge {
  display:flex; align-items:center; gap:.38rem;
  background:rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.3);
  color:#f87171; font-weight:700; font-size:.7rem; letter-spacing:.06em;
  padding:.24rem .7rem; border-radius:50rem;
}
.se-live-badge .dot { width:7px;height:7px;border-radius:50%;background:#ef4444; }
.se-live-badge.on { background:rgba(34,197,94,.12); border-color:rgba(34,197,94,.3); color:#4ade80; }
.se-live-badge.on .dot { background:#22c55e; animation:blink 1.2s infinite; }
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}

.se-cam-label { font-weight:700; font-size:.78rem; letter-spacing:.06em; color:#38bdf8; text-transform:uppercase; }

.se-autofocus-pill {
  display:flex; align-items:center; gap:.35rem;
  background:rgba(13,148,136,.1); border:1px solid rgba(13,148,136,.28);
  color:#2dd4bf; font-size:.72rem; font-weight:600;
  padding:.24rem .7rem; border-radius:50rem;
}

/* Scanner viewport */
#qrReaderAdmin {
  width:100%; aspect-ratio:4/3;
  background:#020a10;
  position:relative;
  display:flex; align-items:center; justify-content:center;
  overflow:hidden;
}
/* Green scan frame corners */
.qr-frame {
  position:absolute; width:200px; height:200px;
  pointer-events:none;
}
.qr-frame .c { position:absolute; width:22px; height:22px; border-color:#22c55e; border-style:solid; }
.qr-frame .c.tl { top:0; left:0; border-width:3px 0 0 3px; border-radius:4px 0 0 0; }
.qr-frame .c.tr { top:0; right:0; border-width:3px 3px 0 0; border-radius:0 4px 0 0; }
.qr-frame .c.bl { bottom:0; left:0; border-width:0 0 3px 3px; border-radius:0 0 0 4px; }
.qr-frame .c.br { bottom:0; right:0; border-width:0 3px 3px 0; border-radius:0 0 4px 0; }
.qr-scanline {
  position:absolute; left:0; right:0; height:2px;
  background:linear-gradient(90deg, transparent, #22c55e 30%, rgba(34,197,94,.8) 50%, #22c55e 70%, transparent);
  box-shadow:0 0 8px rgba(34,197,94,.7);
  animation:qrScan 2.2s ease-in-out infinite;
}
@keyframes qrScan{0%{top:0;opacity:.8}50%{top:200px;opacity:1}100%{top:0;opacity:.8}}
/* html5-qrcode internal overrides */
#qrReaderAdmin video { width:100% !important; height:100% !important; object-fit:cover !important; }
#qrReaderAdmin canvas { display:none !important; }

/* Align hint */
.se-align-hint {
  padding:.65rem 1.1rem; text-align:center;
  font-size:.8rem; color:#38bdf8; font-weight:500;
  display:flex; align-items:center; justify-content:center; gap:.4rem;
  border-top:1px solid rgba(255,255,255,.06);
}

/* Camera buttons */
.se-cam-btns { padding:.85rem 1.1rem; display:flex; gap:.7rem; border-top:1px solid rgba(255,255,255,.07); }
.btn-se-start {
  display:flex; align-items:center; gap:.45rem;
  padding:.62rem 1.25rem; border-radius:.65rem; border:none;
  background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff;
  font-weight:700; font-size:.88rem; cursor:pointer;
  box-shadow:0 4px 14px -3px rgba(37,99,235,.55);
  transition:filter .15s,transform .15s;
}
.btn-se-start:hover { filter:brightness(1.1); transform:translateY(-1px); }
.btn-se-start:disabled { opacity:.5; cursor:not-allowed; transform:none; }
.btn-se-stop {
  display:flex; align-items:center; gap:.45rem;
  padding:.62rem 1.25rem; border-radius:.65rem;
  border:1.5px solid rgba(255,255,255,.15);
  background:rgba(255,255,255,.04); color:#94a3b8;
  font-weight:600; font-size:.88rem; cursor:pointer;
  transition:background .15s,border-color .15s;
}
.btn-se-stop:hover { background:rgba(255,255,255,.08); border-color:rgba(255,255,255,.25); color:#e8edf5; }
.btn-se-stop:disabled { opacity:.45; cursor:not-allowed; }

/* ─── Right panel: OR + manual ─── */
.se-right-panel {
  display:flex; flex-direction:column;
  justify-content:center;
  padding-top:1rem;
}
.se-or-row { display:flex; align-items:flex-start; gap:1rem; }
.se-or-circle {
  width:40px; height:40px; border-radius:50%; flex-shrink:0;
  background:rgba(37,99,235,.12); border:2px solid rgba(56,130,235,.35);
  display:flex; align-items:center; justify-content:center;
  font-weight:800; font-size:.82rem; color:#60a5fa; margin-top:.3rem;
}
.se-manual-wrap { flex:1; }
.se-manual-label { font-size:.85rem; color:#8aa0c4; margin-bottom:.65rem; }
.se-manual-input-row { display:flex; gap:.65rem; }
.se-manual-input-row .tok-wrap { flex:1; position:relative; }
.se-manual-input-row .tok-wrap i {
  position:absolute; left:.9rem; top:50%; transform:translateY(-50%);
  color:#64748b; font-size:.95rem; pointer-events:none;
}
.se-manual-input-row input {
  width:100%; padding:.78rem 1rem .78rem 2.6rem;
  background:rgba(255,255,255,.05); border:1.5px solid rgba(255,255,255,.12);
  border-radius:.75rem; color:#e8edf5; font-size:.92rem; outline:none;
  transition:border-color .2s,box-shadow .2s;
  font-family:'Inter',sans-serif;
}
.se-manual-input-row input::placeholder { color:#475569; }
.se-manual-input-row input:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.18); background:rgba(37,99,235,.06); }
.btn-se-process {
  padding:.78rem 1.5rem; border:none; border-radius:.75rem;
  background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff;
  font-weight:700; font-size:.92rem; cursor:pointer;
  box-shadow:0 4px 14px -3px rgba(37,99,235,.55); white-space:nowrap;
  transition:filter .15s,transform .15s;
}
.btn-se-process:hover { filter:brightness(1.1); transform:translateY(-1px); }

/* Result area */
.se-result { margin-top:1.4rem; }
.se-receipt {
  background:rgba(34,197,94,.08); border:1.5px solid rgba(34,197,94,.28);
  border-radius:.85rem; padding:1.3rem 1.5rem; color:#e8edf5;
}
.sr-title { display:flex; align-items:center; gap:.5rem; font-weight:800; color:#4ade80; font-size:.95rem; margin-bottom:1rem; }
.sr-row { display:flex; justify-content:space-between; padding:.38rem 0; border-bottom:1px solid rgba(255,255,255,.05); font-size:.88rem; }
.sr-row:last-of-type { border-bottom:none; }
.sr-row .rl { color:#8aa0c4; }
.sr-row .rv { font-weight:600; color:#e8edf5; }
.sr-fee { font-size:1.8rem; font-weight:900; color:#4ade80; text-align:center; padding:.9rem 0 .3rem; letter-spacing:-.03em; }
.se-error {
  background:rgba(239,68,68,.1); border:1.5px solid rgba(239,68,68,.28);
  border-radius:.75rem; padding:.85rem 1.1rem; color:#fca5a5; font-size:.88rem;
}

/* ─── Features strip ─── */
.se-features {
  display:grid; grid-template-columns:repeat(3,1fr); gap:1.2rem;
  background:rgba(8,16,36,.85); border:1px solid rgba(56,130,235,.18);
  border-radius:1rem; padding:1.6rem 1.8rem;
}
@media(max-width:700px){ .se-features{ grid-template-columns:1fr; } }
.se-feat { display:flex; gap:1rem; align-items:flex-start; }
.se-feat-icon {
  width:46px; height:46px; border-radius:50%; flex-shrink:0;
  background:rgba(37,99,235,.14); border:1px solid rgba(56,130,235,.28);
  display:flex; align-items:center; justify-content:center;
  color:#38bdf8; font-size:1.2rem;
}
.se-feat .ft { font-weight:700; font-size:.92rem; color:#38bdf8; margin-bottom:.25rem; }
.se-feat .fs { font-size:.8rem; color:#7691c0; line-height:1.45; }
</style>

<div class="se-page">

  <!-- Title -->
  <div class="se-title-row">
    <div class="se-title-icon"><i class="bi bi-qr-code-scan"></i></div>
    <h4>Scan Vehicle Exit QR Code</h4>
  </div>
  <p class="se-subtitle">
    Point the camera at the driver's QR code (shown in their app or printed at entry). The slot will be released and the receipt generated automatically.
  </p>

  <!-- Payment method -->
  <div class="se-payment-group">
    <label>Payment Method</label>
    <div class="se-payment-select-wrap">
      <i class="bi bi-credit-card pay-icon"></i>
      <select id="paymentMethod" onchange="toggleCardFields()">
        <option value="cash">Cash</option>
        <option value="card">Card</option>
        <option value="online">Online</option>
      </select>
      <i class="bi bi-chevron-down caret"></i>
    </div>

    <!-- Card details (shown only when Card is selected) -->
    <div class="se-card-fields" id="cardFields">
      <div class="cf-title"><i class="bi bi-credit-card-2-front"></i> Card Details</div>
      <div class="cf-row">
        <div class="cf-group">
          <i class="bi bi-credit-card"></i>
          <input type="text" id="cardNumber" placeholder="Card Number" inputmode="numeric" maxlength="19" autocomplete="off">
        </div>
      </div>
      <div class="cf-row">
        <div class="cf-group">
          <i class="bi bi-person"></i>
          <input type="text" id="cardName" placeholder="Cardholder Name" autocomplete="off">
        </div>
        <div class="cf-group small">
          <i class="bi bi-calendar3"></i>
          <input type="text" id="cardExpiry" placeholder="MM/YY" maxlength="5" autocomplete="off">
        </div>
        <div class="cf-group small">
          <i class="bi bi-lock"></i>
          <input type="password" id="cardCvv" placeholder="CVV" inputmode="numeric" maxlength="4" autocomplete="off">
        </div>
      </div>
      <div class="cf-hint"><i class="bi bi-shield-lock me-1"></i>Card details are used only to confirm this payment and are not stored.</div>
      <div class="se-card-err" id="cardErr"></div>
    </div>
  </div>

  <!-- Camera + Manual grid -->
  <div class="se-main-grid">

    <!-- Camera card -->
    <div class="se-cam-card">
      <div class="se-cam-topbar">
        <div class="se-live-badge" id="liveBadge"><span class="dot"></span>LIVE</div>
        <div class="se-cam-label">Camera Preview</div>
        <div class="se-autofocus-pill"><i class="bi bi-crosshair"></i> Auto Focus</div>
      </div>

      <!-- Scanner -->
      <div id="qrReaderAdmin" style="position:relative;">
        <div class="qr-frame">
          <div class="c tl"></div><div class="c tr"></div>
          <div class="c bl"></div><div class="c br"></div>
          <div class="qr-scanline"></div>
        </div>
      </div>

      <div class="se-align-hint"><i class="bi bi-info-circle"></i> Align QR code within the frame</div>

      <div class="se-cam-btns">
        <button class="btn-se-start" id="startScanBtn"><i class="bi bi-camera-video-fill"></i> Start Camera</button>
        <button class="btn-se-stop"  id="stopScanBtn"  disabled><i class="bi bi-stop-circle"></i> Stop Camera</button>
      </div>
    </div>

    <!-- Right: OR + manual token -->
    <div class="se-right-panel">
      <div class="se-or-row">
        <div class="se-or-circle">OR</div>
        <div class="se-manual-wrap">
          <div class="se-manual-label">Or enter the QR token manually:</div>
          <form id="manualForm" novalidate>
            <div class="se-manual-input-row">
              <div class="tok-wrap">
                <i class="bi bi-qr-code"></i>
                <input type="text" id="manualToken" placeholder="Paste full 40-char QR token here…" autocomplete="off" spellcheck="false" oninput="showTokenLen(this.value)">
              </div>
              <button type="submit" class="btn-se-process">Process</button>
            </div>
            <div id="tokenLenHint" style="font-size:.74rem;color:#7691c0;margin-top:.35rem;display:none;">
              <span id="tokenLenNum">0</span>/40 characters — 
              <span id="tokenLenStatus"></span>
            </div>
            <div style="font-size:.72rem;color:#5a6a88;margin-top:.25rem;">
              <i class="bi bi-info-circle me-1"></i>Get token from: Vehicle Entry page → QR result (click to copy)

            </div>
          </form>
        </div>
      </div>

      <!-- Result / receipt -->
      <div class="se-result" id="scanResult"></div>
    </div>

  </div>

  <!-- Features strip -->
  <div class="se-features">
    <div class="se-feat">
      <div class="se-feat-icon"><i class="bi bi-shield-check-fill"></i></div>
      <div>
        <div class="ft">Secure &amp; Fast</div>
        <div class="fs">Encrypted QR validation for secure exit processing.</div>
      </div>
    </div>
    <div class="se-feat">
      <div class="se-feat-icon"><i class="bi bi-lightning-charge-fill"></i></div>
      <div>
        <div class="ft">Instant Processing</div>
        <div class="fs">Real-time slot release and automatic receipt generation.</div>
      </div>
    </div>
    <div class="se-feat">
      <div class="se-feat-icon"><i class="bi bi-file-earmark-check-fill"></i></div>
      <div>
        <div class="ft">Digital Records</div>
        <div class="fs">All exit transactions are logged and easy to manage.</div>
      </div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
const csrfToken = '<?= csrf_token() ?>';
let html5QrCode = null;
let processing  = false;

function renderResult(html, ok) {
  document.getElementById('scanResult').innerHTML = ok
    ? `<div class="se-receipt">${html}</div>`
    : `<div class="se-error"><i class="bi bi-x-circle-fill me-2"></i>${html}</div>`;
}

function receiptHtml(r) {
  const dur = r.duration_minutes;
  const h = Math.floor(dur/60), m = dur%60;
  const durStr = h > 0 ? `${h}h${m > 0 ? ' ' + m + 'min' : ''}` : `${m} min`;
  return `<div class="sr-title"><i class="bi bi-check-circle-fill"></i>Exit Processed Successfully</div>
    <div class="sr-row"><span class="rl">Plate Number</span><span class="rv">${r.plate_number}</span></div>
    <div class="sr-row"><span class="rl">Slot</span><span class="rv">${r.slot_code}</span></div>
    <div class="sr-row"><span class="rl">Entry Time</span><span class="rv">${r.entry_time}</span></div>
    <div class="sr-row"><span class="rl">Exit Time</span><span class="rv">${r.exit_time}</span></div>
    <div class="sr-row"><span class="rl">Duration</span><span class="rv">${durStr}</span></div>
    <div class="sr-row"><span class="rl">Payment</span><span class="rv" style="text-transform:capitalize;">${r.payment_method}</span></div>
    ${r.transaction_ref ? `<div class="sr-row"><span class="rl">Reference</span><span class="rv">${r.transaction_ref}</span></div>` : ''}
    ${r.loyalty_discount_percent > 0 ? `<div class="sr-row"><span class="rl">Loyalty Discount</span><span class="rv" style="color:#22c55e;">-${r.loyalty_discount_percent}% (frequent parker)</span></div>` : ''}
    <div class="sr-fee">${r.fee_formatted}</div>`;
}

function toggleCardFields() {
  const isCard = document.getElementById('paymentMethod').value === 'card';
  document.getElementById('cardFields').classList.toggle('show', isCard);
  if (!isCard) document.getElementById('cardErr').style.display = 'none';
}

// Format card number as user types: 1234 5678 9012 3456
document.getElementById('cardNumber').addEventListener('input', function() {
  let v = this.value.replace(/\D/g, '').slice(0, 16);
  this.value = v.replace(/(.{4})/g, '$1 ').trim();
});
// Format expiry as MM/YY
document.getElementById('cardExpiry').addEventListener('input', function() {
  let v = this.value.replace(/\D/g, '').slice(0, 4);
  if (v.length > 2) v = v.slice(0, 2) + '/' + v.slice(2);
  this.value = v;
});
document.getElementById('cardCvv').addEventListener('input', function() {
  this.value = this.value.replace(/\D/g, '').slice(0, 4);
});

function validateCardFields() {
  const num = document.getElementById('cardNumber').value.replace(/\s/g, '');
  const name = document.getElementById('cardName').value.trim();
  const exp = document.getElementById('cardExpiry').value.trim();
  const cvv = document.getElementById('cardCvv').value.trim();
  const errEl = document.getElementById('cardErr');
  let err = '';
  if (num.length < 13 || num.length > 16) err = 'Enter a valid card number.';
  else if (!name) err = 'Enter the cardholder name.';
  else if (!/^\d{2}\/\d{2}$/.test(exp)) err = 'Enter expiry as MM/YY.';
  else if (cvv.length < 3) err = 'Enter a valid CVV.';
  if (err) { errEl.textContent = err; errEl.style.display = 'block'; return null; }
  errEl.style.display = 'none';
  return { last4: num.slice(-4), name, exp };
}

async function processToken(token) {
  if (processing || !token || token.trim() === '') return;

  const paymentMethod = document.getElementById('paymentMethod').value;
  let cardRef = null;
  if (paymentMethod === 'card') {
    cardRef = validateCardFields();
    if (!cardRef) return; // validation failed — stop, don't submit
  }

  processing = true;
  renderResult('<span class="spinner-border spinner-border-sm me-2"></span>Processing exit…', true);
  try {
    const res = await fetch('<?= BASE_URL ?>api/exit.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        csrf_token: csrfToken,
        qr_token: token.trim(),
        payment_method: paymentMethod,
        transaction_ref: cardRef ? ('Card **** ' + cardRef.last4) : null
      })
    });
    const data = await res.json();
    if (data.success) {
      renderResult(receiptHtml(data.receipt), true);
      document.getElementById('manualToken').value = '';
      document.getElementById('tokenLenHint').style.display = 'none';
      document.getElementById('cardNumber').value = '';
      document.getElementById('cardName').value = '';
      document.getElementById('cardExpiry').value = '';
      document.getElementById('cardCvv').value = '';
      document.getElementById('liveBadge').classList.remove('on');
      if (html5QrCode && html5QrCode.isScanning) {
        await html5QrCode.pause(true);
        setTimeout(() => { if (html5QrCode) html5QrCode.resume(); processing = false; }, 3000);
      } else { processing = false; }
    } else {
      renderResult(data.message || 'Could not process exit.', false);
      processing = false;
    }
  } catch(e) {
    renderResult('Network error while processing exit.', false);
    processing = false;
  }
}

document.getElementById('startScanBtn').addEventListener('click', async () => {
  html5QrCode = new Html5Qrcode('qrReaderAdmin', {
    formatsToSupport: [ Html5QrcodeSupportedFormats.QR_CODE ],
    experimentalFeatures: { useBarCodeDetectorIfSupported: true },
    verbose: false
  });
  try {
    await html5QrCode.start(
      { facingMode: 'environment' },
      {
        fps: 10,
        aspectRatio: 4 / 3, // must match the #qrReaderAdmin container's aspect-ratio so the box lines up with what's shown
        qrbox: (viewfinderWidth, viewfinderHeight) => {
          const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
          const size = Math.floor(minEdge * 0.7); // scale to the real video size instead of a fixed 200px
          return { width: size, height: size };
        },
        videoConstraints: {
          facingMode: 'environment',
          focusMode: 'continuous',           // keeps refocusing as the QR is moved closer/further
          advanced: [{ focusMode: 'continuous' }]
        }
      },
      (decoded) => processToken(decoded)
    );

    // Best-effort: explicitly push continuous autofocus onto the running
    // camera track. Some browsers ignore focusMode in the initial
    // getUserMedia constraints but accept it via applyConstraints after
    // the stream is live.
    try {
      const caps = html5QrCode.getRunningTrackCapabilities?.();
      if (caps && caps.focusMode && caps.focusMode.includes('continuous')) {
        await html5QrCode.applyVideoConstraints({ advanced: [{ focusMode: 'continuous' }] });
      }
    } catch (e) { /* not supported on this device/browser — ignore */ }

    document.getElementById('startScanBtn').disabled = true;
    document.getElementById('stopScanBtn').disabled  = false;
    document.getElementById('liveBadge').classList.add('on');
  } catch(err) {
    renderResult('Could not access camera: ' + err, false);
  }
});

// Tap-to-refocus fallback: on devices/browsers that don't support continuous
// autofocus, tapping the video nudges the camera to refocus on what's held
// in front of it (works around cameras that only focus once on start).
document.getElementById('qrReaderAdmin').addEventListener('click', async () => {
  if (!html5QrCode) return;
  try {
    const caps = html5QrCode.getRunningTrackCapabilities?.();
    if (caps && caps.focusMode) {
      await html5QrCode.applyVideoConstraints({
        advanced: [{ focusMode: caps.focusMode.includes('single-shot') ? 'single-shot' : 'continuous' }]
      });
    }
  } catch (e) { /* ignore — not all browsers expose focus control */ }
});

document.getElementById('stopScanBtn').addEventListener('click', async () => {
  if (html5QrCode) { await html5QrCode.stop(); html5QrCode.clear(); html5QrCode = null; }
  document.getElementById('startScanBtn').disabled = false;
  document.getElementById('stopScanBtn').disabled  = true;
  document.getElementById('liveBadge').classList.remove('on');
});

document.getElementById('manualForm').addEventListener('submit', (e) => {
  e.preventDefault();
  processToken(document.getElementById('manualToken').value);
});

function showTokenLen(v) {
  const hint = document.getElementById('tokenLenHint');
  const numEl = document.getElementById('tokenLenNum');
  const statusEl = document.getElementById('tokenLenStatus');
  if (!hint) return;
  const len = v.trim().length;
  numEl.textContent = len;
  hint.style.display = len > 0 ? 'block' : 'none';
  if (len === 40) {
    statusEl.textContent = '✓ Valid length';
    statusEl.style.color = '#4ade80';
  } else if (len > 40) {
    statusEl.textContent = '⚠ Too long';
    statusEl.style.color = '#f59e0b';
  } else {
    statusEl.textContent = 'Keep pasting…';
    statusEl.style.color = '#7691c0';
  }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

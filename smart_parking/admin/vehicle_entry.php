<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$admin = require_admin_login();

$pdo = db();
$logStmt = $pdo->prepare(
    "SELECT pr.record_id, pr.plate_number_detected, pr.entry_time, pr.status,
            s.slot_code, v.vehicle_type, u.full_name AS owner_name, q.code_value AS qr_token
     FROM parking_records pr
     JOIN parking_slots s ON s.slot_id = pr.slot_id
     LEFT JOIN vehicles v ON v.vehicle_id = pr.vehicle_id
     LEFT JOIN users u ON u.user_id = v.user_id
     LEFT JOIN qr_codes q ON q.qr_id = pr.qr_id
     WHERE DATE(pr.entry_time) = CURDATE()
     ORDER BY pr.entry_time DESC LIMIT 50"
);
$logStmt->execute();
$todayLog = $logStmt->fetchAll();

$pageTitle = 'Vehicle Entry';
$activeNav = 'vehicle_entry';
include __DIR__ . '/includes/header.php';
?>

<style>
.ve-grid { display: grid; grid-template-columns: 1fr 340px; gap: 1.4rem; align-items: start; }
@media (max-width: 1100px) { .ve-grid { grid-template-columns: 1fr; } }

/* ── Camera card ── */
.ve-cam-card { background: var(--pv-card); border: 1px solid rgba(56,130,235,.18); border-radius: 1rem; overflow: hidden; }
.ve-cam-head {
  padding: .9rem 1.25rem; display: flex; align-items: center; justify-content: space-between;
  border-bottom: 1px solid rgba(255,255,255,.06);
}
.ve-cam-head .lbl { display:flex; align-items:center; gap:.55rem; font-weight:700; font-size:.95rem; color:#e8edf5; }
.ve-cam-head .lbl i { color:#38bdf8; font-size:1.05rem; }
.ve-live-pill {
  display:flex; align-items:center; gap:.4rem;
  background: rgba(34,197,94,.12); border:1px solid rgba(34,197,94,.35);
  color:#4ade80; font-weight:700; font-size:.72rem; letter-spacing:.04em;
  padding:.28rem .75rem; border-radius:50rem;
}
.ve-live-pill .dot { width:7px;height:7px;border-radius:50%;background:#22c55e; animation: blink 1.4s ease-in-out infinite; }
.ve-live-pill.off { background: rgba(239,68,68,.1); border-color: rgba(239,68,68,.3); color:#f87171; }
.ve-live-pill.off .dot { background:#ef4444; animation:none; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.35} }

#camViewport {
  background: #050a16; width: 100%; aspect-ratio: 16/9; position: relative; overflow: hidden;
  display: flex; align-items: center; justify-content: center;
}
#camVideo { width: 100%; height: 100%; object-fit: cover; display: none; }
#camCanvas { position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; }
#camPlaceholder { text-align: center; color: #475569; }
#camPlaceholder i { font-size: 3.2rem; display: block; margin-bottom: .5rem; color: #38495f; }
#camPlaceholder p { font-size: .85rem; color: #64748b; }

.scan-overlay {
  position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%);
  width: 56%; aspect-ratio: 3/1; border: 2px solid #22c55e;
  border-radius: .5rem; pointer-events: none;
  box-shadow: 0 0 30px rgba(34,197,94,.25);
}
.scan-overlay .corner { position: absolute; width: 18px; height: 18px; border-color: #22c55e; border-style: solid; }
.scan-overlay .corner.tl { top: -2px; left: -2px; border-width: 3px 0 0 3px; border-radius: 4px 0 0 0; }
.scan-overlay .corner.tr { top: -2px; right: -2px; border-width: 3px 3px 0 0; border-radius: 0 4px 0 0; }
.scan-overlay .corner.bl { bottom: -2px; left: -2px; border-width: 0 0 3px 3px; border-radius: 0 0 0 4px; }
.scan-overlay .corner.br { bottom: -2px; right: -2px; border-width: 0 3px 3px 0; border-radius: 0 0 4px 0; }
.scan-line { position: absolute; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, transparent, #22c55e, transparent); animation: scanLine 2s ease-in-out infinite; }
@keyframes scanLine { 0%{top:4px;opacity:1} 95%{top:calc(100% - 6px);opacity:1} 100%{top:4px;opacity:0} }

#plateDetectedBox {
  position: absolute; bottom: 12px; left: 50%; transform: translateX(-50%);
  background: rgba(5,10,22,.9); backdrop-filter: blur(8px);
  border: 1.5px solid #22c55e; border-radius: .6rem;
  padding: .4rem 1.1rem; color: #4ade80; font-weight: 800; font-size: 1.2rem;
  font-family: 'Inter', monospace; letter-spacing: .12em;
  display: none;
}

/* Status / detection bar */
.ve-status-bar {
  display: flex; align-items: center; justify-content: space-between;
  padding: .9rem 1.25rem; border-top: 1px solid rgba(255,255,255,.06);
  font-size: .88rem;
}
.ve-status-left { display:flex; align-items:center; gap:.55rem; color:#4ade80; font-weight:600; }
.ve-status-left i { font-size:1.1rem; }
.ve-status-left.idle { color: #64748b; }
.ve-plate-pill {
  background: rgba(56,130,235,.12); border:1px solid rgba(56,130,235,.3);
  color:#93c5fd; font-weight:700; font-family: monospace; letter-spacing:.08em;
  padding:.32rem .8rem; border-radius:.5rem; font-size:.85rem;
}

/* Controls */
.ve-controls { padding: .9rem 1.25rem; display: flex; gap: .6rem; flex-wrap: wrap; border-top:1px solid rgba(255,255,255,.06); }
.btn-ve { display: flex; align-items: center; gap: .45rem; padding: .55rem 1.05rem; border-radius: .65rem; font-size: .87rem; font-weight: 600; cursor: pointer; border: 1.5px solid transparent; transition: all .15s ease; }
.btn-ve.start  { background: linear-gradient(135deg,#0d9488,#0369a1); color:#fff; border:none; box-shadow:0 3px 12px -2px rgba(13,148,136,.45); }
.btn-ve.stop   { background:transparent; color:#f87171; border-color:rgba(239,68,68,.4); }
.btn-ve.scan   { background: linear-gradient(135deg,#f59e0b,#c2410c); color:#fff; border:none; box-shadow:0 3px 12px -2px rgba(245,158,11,.4); }
.btn-ve.reset  { background:transparent; color:#94a3b8; border-color:rgba(255,255,255,.15); }
.btn-ve.reset:hover { background: rgba(255,255,255,.05); }

/* ── How it works panel ── */
.ve-howitworks { background: var(--pv-card); border: 1px solid rgba(56,130,235,.18); border-radius: 1rem; padding: 1.3rem 1.3rem 1.5rem; }
.ve-howitworks .hiw-title { display:flex; align-items:center; gap:.55rem; font-weight:700; font-size:.95rem; color:#e8edf5; margin-bottom: 1.2rem; }
.ve-howitworks .hiw-title i { color:#38bdf8; font-size:1.05rem; }
.hiw-step { display:flex; gap:.85rem; margin-bottom: 1.3rem; }
.hiw-step:last-child { margin-bottom: 0; }
.hiw-icon { width:42px; height:42px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:1.1rem; color:#fff; }
.hiw-icon.s1 { background: linear-gradient(135deg,#22c55e,#15803d); }
.hiw-icon.s2 { background: linear-gradient(135deg,#3b82f6,#1d4ed8); }
.hiw-icon.s3 { background: linear-gradient(135deg,#8b5cf6,#6d28d9); }
.hiw-txt .ht { font-weight:700; font-size:.92rem; color:#e8edf5; margin-bottom:.2rem; }
.hiw-txt .hs { font-size:.8rem; color:#8aa0c4; line-height:1.4; }

/* ── Details card ── */
.ve-details-card { background: var(--pv-card); border: 1px solid rgba(56,130,235,.18); border-radius: 1rem; padding: 1.4rem 1.5rem; margin-top: 1.3rem; }
.ve-details-card h6 { display:flex; align-items:center; gap:.55rem; font-weight:700; margin-bottom:1.2rem; font-size:.98rem; color:#e8edf5; }
.ve-details-card h6 i { color:#38bdf8; }
.ve-field-row { display: grid; grid-template-columns: 1fr 1fr; gap: .9rem; margin-bottom: .9rem; }
.ve-field-group label { display:block; font-size:.74rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#7691c0; margin-bottom:.4rem; }
.ve-field-group input, .ve-field-group select {
  width:100%; padding:.7rem .95rem; border:1.5px solid rgba(255,255,255,.12); border-radius:.65rem;
  font-size:.92rem; background:rgba(255,255,255,.04); outline:none; color:#e8edf5;
  transition: border-color .2s, box-shadow .2s, background .2s;
}
.ve-field-group input::placeholder { color: #5a6a88; }
.ve-field-group input:focus, .ve-field-group select:focus {
  border-color: #0d9488; box-shadow: 0 0 0 3px rgba(13,148,136,.18); background: rgba(13,148,136,.05);
}
.ve-field-group select option { background: #0d1a38; color:#e8edf5; }

.ve-owner-strip {
  background: rgba(13,148,136,.1); border: 1px solid rgba(13,148,136,.28);
  border-radius: .7rem; padding: .7rem 1rem; margin-bottom: .9rem;
  display: flex; align-items: center; gap: .65rem; font-size: .88rem;
}
.ve-owner-strip i { color: #2dd4bf; font-size: 1.1rem; }
.ve-owner-strip .name { font-weight: 700; color:#e8edf5; }
.ve-owner-strip .email { color: #8aa0c4; font-size: .8rem; }

.ve-submit-btn {
  width: 100%; padding: .9rem; border: none; border-radius: .75rem; margin-top: .4rem;
  background: linear-gradient(90deg, #0d9488, #2563eb);
  color: #fff; font-weight: 700; font-size: 1rem; cursor: pointer;
  box-shadow: 0 6px 20px -4px rgba(13,148,136,.5);
  transition: transform .15s, box-shadow .15s;
  display: flex; align-items: center; justify-content: center; gap: .55rem;
}
.ve-submit-btn:hover { transform: translateY(-1px); box-shadow: 0 9px 26px -4px rgba(37,99,235,.6); }
.ve-submit-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }

#qrResultBox {
  border: 1.5px dashed rgba(56,189,248,.45); border-radius:.85rem;
  padding: 1.1rem; text-align:center; margin-top:1rem; display:none;
  background: rgba(37,99,235,.06);
}
#qrResultBox h6 { font-weight:700; color:#38bdf8; margin-bottom:.6rem; font-size:.9rem; justify-content:center; }
#qrResultBox .slot-label { font-size:1.1rem; font-weight:800; color:#e8edf5; margin:.3rem 0; }
#qrHolder { display:flex; justify-content:center; }
#qrHolder img { border-radius:.5rem; border:4px solid #fff; box-shadow:0 4px 16px rgba(0,0,0,.4); }
.qr-token-text { font-size:.72rem; color:#8aa0c4; word-break:break-all; margin-top:.5rem; }

#entryAlert { display:none; padding:.7rem 1rem; border-radius:.7rem; font-size:.88rem; font-weight:600; margin-bottom:.9rem; }
#entryAlert.success { background:rgba(34,197,94,.1); color:#86efac; border:1.5px solid rgba(34,197,94,.3); }
#entryAlert.danger  { background:rgba(239,68,68,.1); color:#fca5a5; border:1.5px solid rgba(239,68,68,.3); }

/* ── Log card (hidden but kept for log function, optional display) ── */
.log-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.log-table th { padding: .55rem .85rem; text-transform: uppercase; font-size: .7rem; letter-spacing: .05em; color: #7691c0; border-bottom: 1px solid rgba(255,255,255,.07); white-space: nowrap; }
.log-table td { padding: .6rem .85rem; border-bottom: 1px solid rgba(255,255,255,.04); vertical-align: middle; color:#c8d4ec; }
.log-table tbody tr:hover td { background: rgba(13,148,136,.06); }
.plate-badge { font-family: monospace; font-weight:800; font-size:.78rem; letter-spacing:.08em; background:rgba(13,148,136,.12); color:#2dd4bf; padding:.22rem .6rem; border-radius:.45rem; }
.type-badge { font-size:.72rem; background:rgba(255,255,255,.06); color:#94a3b8; padding:.2rem .55rem; border-radius:.4rem; font-weight:600; }

/* Full QR token display with copy */
.qr-token-full-wrap { margin-top: .7rem; }
.qr-token-full {
  font-family: 'Courier New', monospace;
  font-size: .92rem;
  letter-spacing: .05em;
  color: #ffffff;
  background: rgba(37,99,235,.14);
  border: 1.5px solid rgba(56,189,248,.4);
  border-radius: .65rem;
  padding: .75rem 1rem;
  word-break: break-all;
  cursor: pointer;
  line-height: 1.6;
  transition: background .15s, border-color .15s;
}
.qr-token-full:hover {
  background: rgba(37,99,235,.15);
  border-color: rgba(56,189,248,.7);
}

.qr-action-btns { display:flex; gap:.6rem; margin-top:.9rem; }
.qr-action-btn {
  flex:1; padding:.62rem .9rem; border-radius:.6rem; font-weight:700; font-size:.82rem;
  cursor:pointer; display:flex; align-items:center; justify-content:center; gap:.45rem;
  font-family:'Inter',sans-serif; text-decoration:none; transition:filter .15s,transform .15s;
}
.qr-action-btn.primary { background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; border:none; box-shadow:0 4px 14px -3px rgba(37,99,235,.55); }
.qr-action-btn.primary:hover { filter:brightness(1.1); transform:translateY(-1px); }
.qr-action-btn.ghost { background:rgba(255,255,255,.05); color:#c8d4ec; border:1.5px solid rgba(255,255,255,.14); }
.qr-action-btn.ghost:hover { background:rgba(255,255,255,.09); color:#fff; }

/* ── WhatsApp send block ── */
.wa-share-block { margin-top: 1rem; text-align: left; }
.wa-share-block label {
  display:block; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em;
  color:#7691c0; margin-bottom:.4rem;
}
.wa-share-row { display:flex; gap:.55rem; }
.wa-share-row input {
  flex:1; min-width:0; padding:.65rem .85rem; border:1.5px solid rgba(255,255,255,.12); border-radius:.6rem;
  font-size:.88rem; background:rgba(255,255,255,.04); outline:none; color:#e8edf5;
  transition: border-color .2s, box-shadow .2s, background .2s;
}
.wa-share-row input::placeholder { color:#5a6a88; }
.wa-share-row input:focus { border-color:#25d366; box-shadow:0 0 0 3px rgba(37,211,102,.18); background:rgba(37,211,102,.05); }
.wa-send-btn {
  flex-shrink:0; display:flex; align-items:center; gap:.4rem; padding:0 1.05rem; border:none; border-radius:.6rem;
  background:linear-gradient(135deg,#25d366,#128c4a); color:#fff; font-weight:700; font-size:.85rem; cursor:pointer;
  box-shadow:0 4px 14px -3px rgba(37,211,102,.5); transition:filter .15s, transform .15s;
}
.wa-send-btn:hover { filter:brightness(1.08); transform:translateY(-1px); }
.wa-share-hint { font-size:.72rem; color:#8aa0c4; margin-top:.4rem; }
.wa-reused-pill {
  display:inline-flex; align-items:center; gap:.35rem; margin-top:.6rem; padding:.28rem .75rem;
  background:rgba(245,158,11,.12); border:1px solid rgba(245,158,11,.35); color:#fbbf24;
  font-size:.74rem; font-weight:700; border-radius:50rem;
}

/* ── Email send block (mirrors wa-share-block, blue theme) ── */
.email-share-block { margin-top: .85rem; text-align: left; }
.email-share-block label {
  display:block; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em;
  color:#7691c0; margin-bottom:.4rem;
}
.email-share-row { display:flex; gap:.55rem; }
.email-share-row input {
  flex:1; min-width:0; padding:.65rem .85rem; border:1.5px solid rgba(255,255,255,.12); border-radius:.6rem;
  font-size:.88rem; background:rgba(255,255,255,.04); outline:none; color:#e8edf5;
  transition: border-color .2s, box-shadow .2s, background .2s;
}
.email-share-row input::placeholder { color:#5a6a88; }
.email-share-row input:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.18); background:rgba(59,130,246,.05); }
.email-send-btn {
  flex-shrink:0; display:flex; align-items:center; gap:.4rem; padding:0 1.05rem; border:none; border-radius:.6rem;
  background:linear-gradient(135deg,#3b82f6,#1d4ed8); color:#fff; font-weight:700; font-size:.85rem; cursor:pointer;
  box-shadow:0 4px 14px -3px rgba(59,130,246,.5); transition:filter .15s, transform .15s;
}
.email-send-btn:hover { filter:brightness(1.08); transform:translateY(-1px); }
.email-send-btn:disabled { opacity:.6; cursor:not-allowed; transform:none; }
.email-share-hint { font-size:.72rem; color:#8aa0c4; margin-top:.4rem; }

</style>

<div class="ve-grid">
  <!-- LEFT COLUMN -->
  <div>
    <!-- Camera Card -->
    <div class="ve-cam-card">
      <div class="ve-cam-head">
        <div class="lbl"><i class="bi bi-camera-video-fill"></i>Camera Preview</div>
        <div class="ve-live-pill off" id="livePill"><span class="dot"></span> LIVE</div>
      </div>

      <div id="camViewport">
        <div id="camPlaceholder">
          <i class="bi bi-camera-video-off"></i>
          <p>Camera is off.<br>Click <strong>Start Camera</strong> to begin.</p>
        </div>
        <video id="camVideo" autoplay playsinline muted></video>
        <canvas id="camCanvas"></canvas>
        <div class="scan-overlay" id="scanOverlay" style="display:none;">
          <div class="corner tl"></div><div class="corner tr"></div>
          <div class="corner bl"></div><div class="corner br"></div>
          <div class="scan-line"></div>
        </div>
        <div id="plateDetectedBox"></div>
      </div>

      <!-- Status / detected plate bar -->
      <div class="ve-status-bar">
        <div class="ve-status-left idle" id="statusLeft"><i class="bi bi-circle"></i><span id="statusText">Awaiting detection</span></div>
        <div class="ve-plate-pill" id="statusPlatePill" style="display:none;"></div>
      </div>

      <div class="ve-controls">
        <button class="btn-ve start" id="startBtn" onclick="startCamera()"><i class="bi bi-camera-video-fill"></i> Start Camera</button>
        <button class="btn-ve stop"  id="stopBtn"  onclick="stopCamera()" style="display:none;"><i class="bi bi-stop-circle-fill"></i> Stop</button>
        <button class="btn-ve scan"  id="scanBtn"  style="display:none;" onclick="captureAndScan()"><i class="bi bi-upc-scan"></i> Scan Plate</button>
        <button class="btn-ve reset" onclick="resetAll()" style="margin-left:auto;"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
      </div>
    </div>

    <!-- Details Card -->
    <div class="ve-details-card">
      <h6><i class="bi bi-car-front-fill"></i>Vehicle &amp; Owner Details</h6>

      <div id="entryAlert"></div>

      <div class="ve-owner-strip" id="ownerStrip" style="display:none;">
        <i class="bi bi-person-check-fill"></i>
        <div><div class="name" id="ownerName">—</div><div class="email" id="ownerEmail">—</div></div>
        <span class="badge ms-auto" style="background:rgba(16,185,129,.16);color:#4ade80;">Registered</span>
      </div>

      <div class="ve-field-row">
        <div class="ve-field-group" style="grid-column:1/-1">
          <label>Number Plate *</label>
          <input type="text" id="plateFld" placeholder="E.g. WP CAR-1234" oninput="this.value=this.value.toUpperCase()" onblur="lookupOwner()">
        </div>
      </div>
      <div class="ve-field-row">
        <div class="ve-field-group">
          <label>Vehicle Type *</label>
          <select id="vTypeFld">
            <option value="car">Car</option>
            <option value="van">Van</option>
            <option value="bike">Bike</option>
          </select>
        </div>
        <div class="ve-field-group">
          <label>Color</label>
          <input type="text" id="colorFld" placeholder="Optional">
        </div>
      </div>

      <div id="ownerFields" style="display:none;">
        <div style="font-size:.76rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#f59e0b;margin-bottom:.6rem;">
          <i class="bi bi-plus-circle me-1"></i>New vehicle — enter owner details (optional)
        </div>
        <div class="ve-field-row">
          <div class="ve-field-group">
            <label>Owner Name</label>
            <input type="text" id="ownerNameFld" placeholder="Full name">
          </div>
          <div class="ve-field-group">
            <label>Phone</label>
            <input type="text" id="ownerPhoneFld" placeholder="+94 77 000 0000">
          </div>
        </div>
      </div>

      <div id="qrResultBox">
        <h6 class="d-flex"><i class="bi bi-check-circle-fill me-1"></i>Vehicle Entered — QR Issued</h6>
        <div id="qrHolder"></div>
        <div class="slot-label" id="slotLabel"></div>
        <div class="qr-token-full-wrap">
          <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#7691c0;margin-bottom:.3rem;">Full QR Token (for manual use):</div>
          <div class="qr-token-full" id="qrTokenFull" onclick="copyToken(this)" title="Click to copy">—</div>
          <div style="font-size:.72rem;color:#38bdf8;margin-top:.3rem;" id="copyHint">
  <i class="bi bi-clipboard me-1"></i>Click token to copy &nbsp;·&nbsp; 
  <i class="bi bi-arrow-right-circle me-1" style="color:#f59e0b;"></i>Paste at Exit → Manual Token field
</div>
        </div>
        <div class="qr-action-btns">
          <button type="button" class="qr-action-btn primary" onclick="downloadEntryQr()"><i class="bi bi-download"></i> Download QR</button>
          <a href="#" target="_blank" class="qr-action-btn ghost" id="viewQrPageLink"><i class="bi bi-box-arrow-up-right"></i> View / Print Page</a>
        </div>

        <div id="qrReusedPill" class="wa-reused-pill" style="display:none;"><i class="bi bi-arrow-repeat"></i> Same QR as this plate's earlier visit</div>

        <div class="wa-share-block">
          <label>Send QR to driver via WhatsApp</label>
          <div class="wa-share-row">
            <input type="text" id="waPhoneFld" placeholder="07XXXXXXXX or +94XXXXXXXXX">
            <button type="button" class="wa-send-btn" onclick="sendQrViaWhatsapp()"><i class="bi bi-whatsapp"></i> Send</button>
          </div>
          <div class="wa-share-hint">Opens WhatsApp with a link the driver can open to view/save their QR — no app install needed.</div>
        </div>

        <div class="email-share-block">
          <label>Or email the QR link</label>
          <div class="email-share-row">
            <input type="email" id="ownerEmailFld2" placeholder="owner@example.com">
            <button type="button" class="email-send-btn" id="emailSendBtn" onclick="sendQrViaEmail()"><i class="bi bi-envelope-fill"></i> Send</button>
          </div>
          <div class="email-share-hint" id="emailShareHint">Sends the same QR link by email.</div>
        </div>
      </div>

      <button class="ve-submit-btn" id="submitEntryBtn" onclick="submitEntry()">
        <i class="bi bi-qr-code-scan"></i> Process Entry &amp; Generate QR
      </button>
    </div>
  </div>

  <!-- RIGHT COLUMN: How it works -->
  <div>
    <div class="ve-howitworks">
      <div class="hiw-title"><i class="bi bi-info-circle-fill"></i>How it works</div>

      <div class="hiw-step">
        <div class="hiw-icon s1"><i class="bi bi-camera-video-fill"></i></div>
        <div class="hiw-txt">
          <div class="ht">1. Capture Vehicle</div>
          <div class="hs">Start the camera to capture the vehicle image.</div>
        </div>
      </div>

      <div class="hiw-step">
        <div class="hiw-icon s2"><i class="bi bi-file-earmark-text-fill"></i></div>
        <div class="hiw-txt">
          <div class="ht">2. Enter Details</div>
          <div class="hs">Fill in the vehicle and owner details below.</div>
        </div>
      </div>

      <div class="hiw-step">
        <div class="hiw-icon s3"><i class="bi bi-qr-code"></i></div>
        <div class="hiw-txt">
          <div class="ht">3. Generate QR</div>
          <div class="hs">Click the button below to generate the QR code.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- QR popup modal for log rows (kept for showLogQr function) -->
<div class="modal fade" id="logQrModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content text-center">
      <div class="modal-body p-4">
        <h6 class="fw-bold mb-1">QR — Slot <span id="logQrSlot"></span></h6>
        <div id="logQrHolder" style="display:flex;justify-content:center;margin:1rem 0;"></div>
        <a id="logQrViewLink" href="#" target="_blank" class="btn btn-sm btn-outline-info mb-2 d-inline-flex align-items-center gap-1">
          <i class="bi bi-box-arrow-up-right"></i> View / Download
        </a><br>
        <button class="btn btn-sm btn-outline-primary" onclick="bootstrap.Modal.getInstance(document.getElementById('logQrModal')).hide()">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Hidden today log table (kept in DOM for JS, not visually shown per new design) -->
<div style="display:none;">
  <table class="log-table"><tbody id="logBody">
    <?php foreach ($todayLog as $row): ?>
    <tr>
      <td><span class="plate-badge"><?= e($row['plate_number_detected'] ?? '—') ?></span></td>
      <td><span class="type-badge"><?= e(strtoupper($row['vehicle_type'] ?? 'CAR')) ?></span></td>
      <td><strong><?= e($row['slot_code']) ?></strong></td>
      <td><?= e(date('H:i', strtotime($row['entry_time']))) ?></td>
      <td>
        <?php if ($row['qr_token']): ?>
          <button onclick="showLogQr('<?= e($row['qr_token']) ?>','<?= e($row['slot_code']) ?>')"><i class="bi bi-qr-code"></i></button>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody></table>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script>
const BASE_URL    = "<?= BASE_URL ?>";
const CSRF_TOKEN  = "<?= csrf_token() ?>";
const DEVICE_KEY  = "<?= e(DEVICE_API_KEY) ?>";

let stream = null;
let scanning = false;
let foundOwner = null;

/* ---- Camera ---- */
async function startCamera() {
  try {
    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', width:{ideal:1280}, height:{ideal:720} } });
    const video = document.getElementById('camVideo');
    video.srcObject = stream;
    video.style.display = 'block';
    document.getElementById('camPlaceholder').style.display = 'none';
    document.getElementById('scanOverlay').style.display = 'block';
    document.getElementById('livePill').classList.remove('off');
    document.getElementById('startBtn').style.display = 'none';
    document.getElementById('stopBtn').style.display  = 'flex';
    document.getElementById('scanBtn').style.display  = 'flex';
  } catch(e) {
    alert('Camera access denied or not available. Please use the manual plate field below.');
  }
}

function stopCamera() {
  if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
  const video = document.getElementById('camVideo');
  video.style.display = 'none'; video.srcObject = null;
  document.getElementById('camPlaceholder').style.display = 'block';
  document.getElementById('scanOverlay').style.display = 'none';
  document.getElementById('livePill').classList.add('off');
  document.getElementById('startBtn').style.display = 'flex';
  document.getElementById('stopBtn').style.display  = 'none';
  document.getElementById('scanBtn').style.display  = 'none';
}

/* ---- OCR Plate Scan ---- */
async function captureAndScan() {
  const video = document.getElementById('camVideo');
  const canvas = document.getElementById('camCanvas');
  const btn = document.getElementById('scanBtn');
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Scanning…';
  btn.disabled = true;

  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  const ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0);

  const cropX = canvas.width * 0.15;
  const cropY = canvas.height * 0.45;
  const cropW = canvas.width * 0.70;
  const cropH = canvas.height * 0.45;
  const cropCanvas = document.createElement('canvas');
  cropCanvas.width = cropW; cropCanvas.height = cropH;
  const cc = cropCanvas.getContext('2d');
  cc.filter = 'contrast(1.6) brightness(1.1) grayscale(1)';
  cc.drawImage(canvas, cropX, cropY, cropW, cropH, 0, 0, cropW, cropH);

  try {
    const result = await Tesseract.recognize(cropCanvas, 'eng', {
      tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-',
    });
    const raw = result.data.text.trim().replace(/[^A-Z0-9\-]/gi,'').toUpperCase();
    const match = raw.match(/[A-Z]{1,3}[0-9A-Z\-]{3,10}/);
    const plate = match ? match[0] : raw.slice(0,12);
    if (plate.length >= 4) {
      document.getElementById('plateFld').value = plate;
      showDetectedPlate(plate);
      lookupOwner();
    } else {
      showAlert('Could not read plate clearly. Please type it manually.', 'danger');
    }
  } catch(e) {
    showAlert('OCR error. Please enter plate manually.', 'danger');
  }

  btn.innerHTML = '<i class="bi bi-upc-scan"></i> Scan Plate';
  btn.disabled = false;
}

function showDetectedPlate(plate) {
  const box = document.getElementById('plateDetectedBox');
  box.textContent = plate;
  box.style.display = 'block';
  setTimeout(() => { box.style.display = 'none'; }, 5000);

  const sl = document.getElementById('statusLeft');
  sl.classList.remove('idle');
  sl.innerHTML = '<i class="bi bi-check-circle-fill"></i><span>License Plate Detected</span>';
  const pill = document.getElementById('statusPlatePill');
  pill.style.display = 'inline-block';
  pill.textContent = plate;
}

/* ---- Owner Lookup ---- */
async function lookupOwner() {
  const plate = document.getElementById('plateFld').value.trim().toUpperCase().replace(/[\s\-]/g,'');
  if (plate.length < 3) return;
  foundOwner = null;
  try {
    const r = await fetch(`${BASE_URL}api/lookup_vehicle.php?plate=${encodeURIComponent(plate)}`);
    const d = await r.json();
    // d.vehicle is only ever returned when plate matches an existing record
    // EXACTLY (see api/lookup_vehicle.php) — never a fuzzy/partial match —
    // so it's always safe to auto-fill from it.
    if (d.success && d.vehicle) {
      foundOwner = d.vehicle;
      document.getElementById('vTypeFld').value = d.vehicle.vehicle_type || 'car';
      document.getElementById('colorFld').value = d.vehicle.color || '';
      document.getElementById('ownerFields').style.display = 'none';
      if (d.vehicle.owner) {
        document.getElementById('ownerStrip').style.display = 'flex';
        document.getElementById('ownerName').textContent  = d.vehicle.owner.full_name;
        document.getElementById('ownerEmail').textContent = d.vehicle.owner.email || '';
      } else {
        document.getElementById('ownerStrip').style.display = 'none';
      }
    } else {
      // No exact match for this plate — don't show any other vehicle's details.
      foundOwner = null;
      document.getElementById('ownerStrip').style.display = 'none';
      document.getElementById('ownerFields').style.display = 'block';
    }
  } catch(e) { /* network fail — silently skip lookup */ }
}

/* ---- Submit Entry ---- */
async function submitEntry() {
  const plate = document.getElementById('plateFld').value.trim();
  if (!plate || plate.length < 3) { showAlert('Please enter a valid plate number.','danger'); return; }

  const btn = document.getElementById('submitEntryBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing…';

  try {
    const resp = await fetch(`${BASE_URL}api/entry.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Device-Key': DEVICE_KEY },
      body: JSON.stringify({
        plate_number: plate,
        vehicle_type: document.getElementById('vTypeFld').value,
        color: document.getElementById('colorFld').value.trim() || null,
        owner_name: document.getElementById('ownerNameFld')?.value?.trim() || null,
        owner_phone: document.getElementById('ownerPhoneFld')?.value?.trim() || null,
      })
    });
    const data = await resp.json();

    if (data.success) {
      const slotDisplay = data.display_slot || data.slot_code;
      const subInfo = data.sub_slot ? ` Sub-slot: ${slotDisplay}` : ` Slot: ${slotDisplay}`;
      showAlert(`✓ Entry recorded —${subInfo} (${data.mode === 'reserved' ? 'Reserved' : 'Walk-in'})`, 'success');
      showQr(data.qr_token, data.display_slot || data.slot_code, data);
      prependLog(plate, data.slot_code, data.qr_token);
    } else {
      showAlert(data.message || 'Entry failed.', 'danger');
    }
  } catch(e) {
    showAlert('Network error — check your server connection.', 'danger');
  }
  btn.disabled = false;
  btn.innerHTML = '<i class="bi bi-qr-code-scan"></i> Process Entry & Generate QR';
}

/* ---- QR render ---- */
let currentQrToken = null;
let currentShareUrl = null;

function showQr(token, slot, data) {
  const box = document.getElementById('qrResultBox');
  const holder = document.getElementById('qrHolder');
  holder.innerHTML = '';
  new QRCode(holder, { text: token, width: 170, height: 170, correctLevel: QRCode.CorrectLevel.M });
  document.getElementById('slotLabel').textContent = 'Slot: ' + (data && data.display_slot ? data.display_slot : slot);
  document.getElementById('qrTokenFull').textContent = token;
  document.getElementById('viewQrPageLink').href = `${BASE_URL}admin/qr_view.php?token=${encodeURIComponent(token)}`;

  currentQrToken = token;
  currentShareUrl = (data && data.share_url) ? data.share_url : `${BASE_URL}qr_share.php?token=${encodeURIComponent(token)}`;

  const reusedPill = document.getElementById('qrReusedPill');
  reusedPill.style.display = (data && data.qr_reused) ? 'inline-flex' : 'none';

  // Prefill the WhatsApp number from a matched owner or the new-owner field, if present.
  const phoneGuess = (foundOwner && foundOwner.owner && foundOwner.owner.phone)
    || document.getElementById('ownerPhoneFld')?.value?.trim()
    || '';
  document.getElementById('waPhoneFld').value = phoneGuess;

  // Prefill the email field from a matched owner, if present.
  const emailGuess = (foundOwner && foundOwner.owner && foundOwner.owner.email) || '';
  document.getElementById('ownerEmailFld2').value = emailGuess;
  document.getElementById('emailShareHint').textContent = 'Sends the same QR link by email.';
  document.getElementById('emailShareHint').style.color = '';

  box.style.display = 'block';
  box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/* ---- WhatsApp share ---- */
function normalizePhoneForWhatsapp(raw) {
  let digits = (raw || '').replace(/\D/g, '');
  if (digits.startsWith('0')) {
    digits = '94' + digits.slice(1);        // local 0-prefixed -> country code 94 (Sri Lanka)
  } else if (digits.length === 9) {
    digits = '94' + digits;                 // e.g. 771234567 -> 94771234567
  }
  return digits;
}

function sendQrViaWhatsapp() {
  const raw = document.getElementById('waPhoneFld').value.trim();
  if (!raw) { showAlert('Enter a WhatsApp number first.', 'danger'); return; }
  if (!currentShareUrl) { showAlert('Generate the QR before sending.', 'danger'); return; }

  const phone = normalizePhoneForWhatsapp(raw);
  if (phone.length < 11) { showAlert('Enter a valid phone number.', 'danger'); return; }

  const plate = document.getElementById('plateFld').value.trim();
  const msg = `Hello! Here is your parking QR for vehicle ${plate}.\nShow this QR at the exit gate:\n${currentShareUrl}\n\nThis same QR is reused every time this vehicle parks here — save it for next time.`;
  const waUrl = `https://wa.me/${phone}?text=${encodeURIComponent(msg)}`;
  window.open(waUrl, '_blank');
}

/* ---- Email share ---- */
async function sendQrViaEmail() {
  const email = document.getElementById('ownerEmailFld2').value.trim();
  const hint  = document.getElementById('emailShareHint');
  const btn   = document.getElementById('emailSendBtn');

  if (!email) { showAlert('Enter an email address first.', 'danger'); return; }
  if (!currentQrToken) { showAlert('Generate the QR before sending.', 'danger'); return; }

  const plate = document.getElementById('plateFld').value.trim();
  const slot  = document.getElementById('slotLabel').textContent.replace(/^Slot:\s*/, '');
  const name  = (foundOwner && foundOwner.owner && foundOwner.owner.full_name)
    || document.getElementById('ownerNameFld')?.value?.trim()
    || '';

  btn.disabled = true;
  hint.style.color = '';
  hint.textContent = 'Sending…';

  try {
    const resp = await fetch(`${BASE_URL}api/send_qr_email.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, name, plate_number: plate, slot_label: slot, qr_token: currentQrToken })
    });
    const data = await resp.json();

    if (data.success) {
      hint.textContent = '✓ Email sent to ' + email;
      hint.style.color = '#25d366';
    } else {
      hint.textContent = data.message || 'Could not send email.';
      hint.style.color = '#f87171';
    }
  } catch (err) {
    hint.textContent = 'Network error — could not reach the server.';
    hint.style.color = '#f87171';
  } finally {
    btn.disabled = false;
  }
}

function downloadEntryQr() {
  const holder = document.getElementById('qrHolder');
  const canvas = holder.querySelector('canvas');
  const img = holder.querySelector('img');
  const token = document.getElementById('qrTokenFull').textContent || 'qr';
  const a = document.createElement('a');
  a.download = 'parkvision-qr-' + token.slice(0, 10) + '.png';
  if (canvas) {
    a.href = canvas.toDataURL('image/png');
  } else if (img) {
    a.href = img.src;
  } else {
    return;
  }
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
}

function showLogQr(token, slot) {
  document.getElementById('logQrSlot').textContent = slot;
  const h = document.getElementById('logQrHolder');
  h.innerHTML = '';
  new QRCode(h, { text: token, width: 200, height: 200, correctLevel: QRCode.CorrectLevel.M });
  document.getElementById('logQrViewLink').href = `${BASE_URL}admin/qr_view.php?token=${encodeURIComponent(token)}`;
  new bootstrap.Modal(document.getElementById('logQrModal')).show();
}

function prependLog(plate, slot, token) {
  const tbody = document.getElementById('logBody');
  if (!tbody) return;
  const tr = document.createElement('tr');
  tr.innerHTML = `<td><span class="plate-badge">${plate}</span></td>
    <td><span class="type-badge">${document.getElementById('vTypeFld').value.toUpperCase()}</span></td>
    <td><strong>${slot}</strong></td><td></td>
    <td><button onclick="showLogQr('${token}','${slot}')"><i class="bi bi-qr-code"></i></button></td>`;
  tbody.insertBefore(tr, tbody.firstChild);
}

function showAlert(msg, type) {
  const el = document.getElementById('entryAlert');
  el.textContent = msg; el.className = type; el.style.display = 'block';
  setTimeout(() => { el.style.display = 'none'; }, 6000);
}

function copyToken() {
  const el = document.getElementById('qrTokenText');
  if (!el || !el.value) return;
  navigator.clipboard.writeText(el.value).then(() => {
    showAlert('✓ Token copied to clipboard!', 'success');
  }).catch(() => {
    el.select(); document.execCommand('copy');
    showAlert('✓ Token copied!', 'success');
  });
}

function resetAll() {
  document.getElementById('plateFld').value = '';
  document.getElementById('colorFld').value = '';
  document.getElementById('ownerNameFld') && (document.getElementById('ownerNameFld').value = '');
  document.getElementById('ownerPhoneFld') && (document.getElementById('ownerPhoneFld').value = '');
  document.getElementById('ownerStrip').style.display = 'none';
  document.getElementById('ownerFields').style.display = 'none';
  document.getElementById('qrResultBox').style.display = 'none';
  document.getElementById('entryAlert').style.display = 'none';
  document.getElementById('plateDetectedBox').style.display = 'none';
  document.getElementById('waPhoneFld').value = '';
  document.getElementById('qrReusedPill').style.display = 'none';
  document.getElementById('ownerEmailFld2').value = '';
  document.getElementById('emailShareHint').textContent = 'Sends the same QR link by email.';
  document.getElementById('emailShareHint').style.color = '';
  currentQrToken = null;
  currentShareUrl = null;
  const sl = document.getElementById('statusLeft');
  sl.classList.add('idle');
  sl.innerHTML = '<i class="bi bi-circle"></i><span>Awaiting detection</span>';
  document.getElementById('statusPlatePill').style.display = 'none';
  foundOwner = null;
}
function copyToken(el) {
  const txt = el.textContent;
  if (!txt || txt === '—') return;
  navigator.clipboard.writeText(txt).then(() => {
    el.style.borderColor = '#22c55e';
    el.style.color = '#4ade80';
    document.getElementById('copyHint').innerHTML = '<i class="bi bi-check2 me-1" style="color:#4ade80;"></i><span style="color:#4ade80;">Copied!</span>';
    setTimeout(() => {
      el.style.borderColor = '';
      el.style.color = '';
      document.getElementById('copyHint').innerHTML = '<i class="bi bi-clipboard me-1"></i>Click token to copy';
    }, 2500);
  }).catch(() => {
    // Fallback for older browsers
    const ta = document.createElement('textarea');
    ta.value = txt; document.body.appendChild(ta);
    ta.select(); document.execCommand('copy');
    document.body.removeChild(ta);
    document.getElementById('copyHint').innerHTML = '<i class="bi bi-check2 me-1" style="color:#4ade80;"></i><span style="color:#4ade80;">Copied!</span>';
  });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

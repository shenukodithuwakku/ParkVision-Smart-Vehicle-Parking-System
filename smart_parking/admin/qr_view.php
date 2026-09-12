<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$admin = require_admin_login();
$pdo = db();

$token = clean_input($_GET['token'] ?? '');
if ($token === '') {
    http_response_code(400);
    die('No QR token provided.');
}

// Look up the QR code and, if it's linked to an entry or reservation, pull display details.
$stmt = $pdo->prepare('SELECT * FROM qr_codes WHERE code_value = ?');
$stmt->execute([$token]);
$qr = $stmt->fetch();

if (!$qr) {
    http_response_code(404);
    die('QR code not found.');
}

$stmt = $pdo->prepare(
    "SELECT pr.entry_time, pr.status, s.slot_code, s.zone, v.plate_number, v.vehicle_type
     FROM parking_records pr
     JOIN parking_slots s ON s.slot_id = pr.slot_id
     LEFT JOIN vehicles v ON v.vehicle_id = pr.vehicle_id
     WHERE pr.qr_id = ? LIMIT 1"
);
$stmt->execute([(int)$qr['qr_id']]);
$record = $stmt->fetch();

if (!$record) {
    $stmt = $pdo->prepare(
        "SELECT r.booking_date, r.arrival_time, r.status, s.slot_code, s.zone, v.plate_number, v.vehicle_type
         FROM reservations r
         JOIN parking_slots s ON s.slot_id = r.slot_id
         LEFT JOIN vehicles v ON v.vehicle_id = r.vehicle_id
         WHERE r.qr_id = ? LIMIT 1"
    );
    $stmt->execute([(int)$qr['qr_id']]);
    $record = $stmt->fetch();
}

$pageTitle = 'QR Code';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>QR Code — ParkVision Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{
  min-height:100vh;background:#070c18;font-family:'Inter',sans-serif;
  display:flex;align-items:center;justify-content:center;padding:2rem 1rem;
}
.qv-card{
  width:100%;max-width:420px;background:rgba(13,20,40,.92);
  border:1px solid rgba(56,130,235,.25);border-radius:1.1rem;
  padding:2rem 1.9rem 1.8rem;text-align:center;
  box-shadow:0 30px 70px rgba(0,0,0,.55);
}
.qv-back{
  display:inline-flex;align-items:center;gap:.4rem;color:#7691c0;font-size:.82rem;
  text-decoration:none;margin-bottom:1.2rem;font-weight:600;
}
.qv-back:hover{color:#38bdf8;}
.qv-title{font-size:1.15rem;font-weight:800;color:#e8edf5;margin-bottom:.2rem;}
.qv-sub{font-size:.82rem;color:#7691c0;margin-bottom:1.4rem;}
.qv-badge{
  display:inline-flex;align-items:center;gap:.35rem;padding:.28rem .85rem;border-radius:50rem;
  font-size:.72rem;font-weight:700;letter-spacing:.04em;margin-bottom:1.2rem;
  background:<?= $qr['is_used'] ? 'rgba(148,163,184,.14);color:#94a3b8;border:1px solid rgba(148,163,184,.3)' : 'rgba(34,197,94,.12);color:#4ade80;border:1px solid rgba(34,197,94,.3)' ?>;
}
#qrBox{
  display:flex;align-items:center;justify-content:center;
  background:#fff;border-radius:.9rem;padding:1.1rem;margin:0 auto 1.3rem;width:fit-content;
}
.qv-details{
  text-align:left;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);
  border-radius:.75rem;padding:.9rem 1.05rem;margin-bottom:1.3rem;font-size:.85rem;
}
.qv-row{display:flex;justify-content:space-between;padding:.3rem 0;border-bottom:1px solid rgba(255,255,255,.05);}
.qv-row:last-child{border-bottom:none;}
.qv-row .l{color:#7691c0;}
.qv-row .v{color:#e8edf5;font-weight:600;}
.qv-token{
  font-family:'Courier New',monospace;font-size:.78rem;color:#93c5fd;background:rgba(37,99,235,.12);
  border:1px solid rgba(56,189,248,.3);border-radius:.6rem;padding:.65rem .8rem;
  word-break:break-all;margin-bottom:1.3rem;cursor:pointer;
}
.qv-btns{display:flex;gap:.65rem;}
.qv-btn{
  flex:1;padding:.75rem 1rem;border-radius:.7rem;border:none;font-weight:700;font-size:.88rem;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.5rem;
  font-family:'Inter',sans-serif;transition:filter .15s,transform .15s;
}
.qv-btn.primary{background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;box-shadow:0 6px 18px -4px rgba(37,99,235,.6);}
.qv-btn.primary:hover{filter:brightness(1.1);transform:translateY(-1px);}
.qv-btn.ghost{background:rgba(255,255,255,.05);color:#c8d4ec;border:1.5px solid rgba(255,255,255,.12);}
.qv-btn.ghost:hover{background:rgba(255,255,255,.09);}
@media print{
  .qv-back,.qv-btns{display:none;}
  body{background:#fff;}
  .qv-card{box-shadow:none;border:none;}
}
</style>
</head>
<body>
<div class="qv-card">
  <a href="<?= BASE_URL ?>admin/vehicle_entry.php" class="qv-back"><i class="bi bi-arrow-left"></i> Back to Vehicle Entry</a>
  <div class="qv-title">Entry QR Code</div>
  <div class="qv-sub">Give this to the driver, or keep it for exit verification.</div>

  <div class="qv-badge"><i class="bi bi-<?= $qr['is_used'] ? 'check-circle' : 'lightning-charge-fill' ?>"></i><?= $qr['is_used'] ? 'Already Used' : 'Active' ?></div>

  <div id="qrBox"></div>

  <?php if ($record): ?>
  <div class="qv-details">
    <?php if (!empty($record['plate_number'])): ?>
    <div class="qv-row"><span class="l">Plate Number</span><span class="v"><?= e($record['plate_number']) ?></span></div>
    <?php endif; ?>
    <?php if (!empty($record['slot_code'])): ?>
    <div class="qv-row"><span class="l">Slot</span><span class="v"><?= e($record['slot_code']) ?> (<?= e($record['zone'] ?? '') ?>)</span></div>
    <?php endif; ?>
    <?php if (!empty($record['entry_time'])): ?>
    <div class="qv-row"><span class="l">Entry Time</span><span class="v"><?= e($record['entry_time']) ?></span></div>
    <?php endif; ?>
    <?php if (!empty($record['booking_date'])): ?>
    <div class="qv-row"><span class="l">Booking</span><span class="v"><?= e($record['booking_date']) ?> <?= e($record['arrival_time'] ?? '') ?></span></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="qv-token" id="tokenText" onclick="copyTok()"><?= e($qr['code_value']) ?></div>

  <div class="qv-btns">
    <button class="qv-btn primary" onclick="downloadQr()"><i class="bi bi-download"></i> Download PNG</button>
    <button class="qv-btn ghost" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
const TOKEN = <?= json_encode($qr['code_value']) ?>;
new QRCode(document.getElementById('qrBox'), { text: TOKEN, width: 220, height: 220, correctLevel: QRCode.CorrectLevel.M });

function downloadQr() {
  // qrcode.js renders either a <canvas> or an <img> depending on the browser
  const box = document.getElementById('qrBox');
  const canvas = box.querySelector('canvas');
  const img = box.querySelector('img');
  const a = document.createElement('a');
  a.download = 'parkvision-qr-' + TOKEN.slice(0, 10) + '.png';
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

function copyTok() {
  navigator.clipboard.writeText(TOKEN).then(() => {
    const el = document.getElementById('tokenText');
    const old = el.style.borderColor;
    el.style.borderColor = '#22c55e';
    setTimeout(() => { el.style.borderColor = old; }, 900);
  });
}
</script>
</body>
</html>

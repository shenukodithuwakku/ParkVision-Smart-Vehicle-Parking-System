<?php
$pageTitle = $pageTitle ?? 'Admin';
$activeNav = $activeNav ?? '';
$navItems  = [
  'dashboard'     =>['icon'=>'bi-speedometer2',          'label'=>'Dashboard',       'href'=>'dashboard.php'],
  'vehicle_entry' =>['icon'=>'bi-arrow-right-square-fill','label'=>'Vehicle Entry',   'href'=>'vehicle_entry.php'],
  'scan_exit'     =>['icon'=>'bi-qr-code-scan',          'label'=>'Scan Exit (QR)',  'href'=>'scan_exit.php'],
  'slots'         =>['icon'=>'bi-grid-3x3-gap',          'label'=>'Manage Slots',    'href'=>'manage_slots.php'],
  'reservations'  =>['icon'=>'bi-calendar-check',        'label'=>'Reservations',    'href'=>'manage_reservations.php'],
  'records'       =>['icon'=>'bi-clock-history',         'label'=>'Parking Records', 'href'=>'manage_records.php'],
  'users'         =>['icon'=>'bi-people',                'label'=>'Users',           'href'=>'manage_users.php'],
  'vehicles'      =>['icon'=>'bi-car-front',             'label'=>'Vehicles',        'href'=>'manage_vehicles.php'],
  'qr_codes'      =>['icon'=>'bi-upc-scan',              'label'=>'QR Codes',        'href'=>'qr_codes.php'],
  'reports'       =>['icon'=>'bi-bar-chart-line',        'label'=>'Reports',         'href'=>'reports.php'],
  'logs'          =>['icon'=>'bi-list-ul',               'label'=>'Activity Logs',   'href'=>'activity_logs.php'],
  'backup'        =>['icon'=>'bi-hdd-network',            'label'=>'Backup & Restore','href'=>'backup.php'],
  'settings'      =>['icon'=>'bi-gear',                  'label'=>'Settings',        'href'=>'settings.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> | Admin | ParkVision</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
<script>
(function(){var t=localStorage.getItem('pv_theme')||'dark';document.documentElement.setAttribute('data-bs-theme',t);})();
</script>
</head>
<body>
<div class="admin-wrapper">

  <aside class="admin-sidebar" id="adminSidebar">
    <div class="brand">
      <div class="brand-pv">
        <span class="bpv-park">Park</span><span class="bpv-vision">Vision</span>
        <span class="bpv-sep">|</span>
        <span class="bpv-panel">Control Panel</span>
      </div>
    </div>
    <nav class="nav flex-column py-1">
      <div class="nav-section">Main</div>
      <?php foreach(['dashboard','vehicle_entry','scan_exit'] as $k): $v=$navItems[$k]; ?>
        <a class="nav-link <?=$activeNav===$k?'active':''?>" href="<?=BASE_URL?>admin/<?=$v['href']?>">
          <i class="bi <?=$v['icon']?>"></i><span><?= e($v['label']) ?></span>
        </a>
      <?php endforeach; ?>
      <div class="nav-section">Parking</div>
      <?php foreach(['slots','reservations','records'] as $k): $v=$navItems[$k]; ?>
        <a class="nav-link <?=$activeNav===$k?'active':''?>" href="<?=BASE_URL?>admin/<?=$v['href']?>">
          <i class="bi <?=$v['icon']?>"></i><span><?= e($v['label']) ?></span>
        </a>
      <?php endforeach; ?>
      <div class="nav-section">Management</div>
      <?php foreach(['users','vehicles','qr_codes','reports','logs','settings'] as $k): $v=$navItems[$k]; ?>
        <a class="nav-link <?=$activeNav===$k?'active':''?>" href="<?=BASE_URL?>admin/<?=$v['href']?>">
          <i class="bi <?=$v['icon']?>"></i><span><?= e($v['label']) ?></span>
        </a>
      <?php endforeach; ?>
      <?php if (($admin['role'] ?? '') === 'super_admin'): $v=$navItems['backup']; ?>
        <a class="nav-link <?=$activeNav==='backup'?'active':''?>" href="<?=BASE_URL?>admin/<?=$v['href']?>">
          <i class="bi <?=$v['icon']?>"></i><span><?= e($v['label']) ?></span>
        </a>
      <?php endif; ?>
      <div style="padding:.4rem .4rem;margin-top:.4rem;border-top:1px solid rgba(255,255,255,.06);">
        <a class="nav-link logout-link" href="<?=BASE_URL?>admin/logout.php">
          <i class="bi bi-box-arrow-right"></i><span>Logout</span>
        </a>
      </div>
    </nav>
  </aside>

  <div class="admin-content">
    <div class="admin-topbar">
      <div class="d-flex align-items-center gap-2">
        <button id="sidebarCollapseBtn" class="us-hamburger" title="Toggle sidebar">
          <i class="bi bi-layout-sidebar-reverse"></i>
        </button>
        <button id="mobileSidebarBtn" class="us-hamburger d-md-none">
          <i class="bi bi-list"></i>
        </button>
        <div class="d-flex align-items-center gap-2 ms-1">
          <span style="width:8px;height:8px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.2);animation:liveBlink 2s infinite;flex-shrink:0;"></span>
          <h6 class="mb-0"><?= e($pageTitle) ?></h6>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="d-none d-sm-flex align-items-center gap-2" style="font-size:.82rem;color:#a8b4cc;">
          <i class="bi bi-person-circle" style="color:#38bdf8;font-size:1rem;"></i>
          <span style="color:#e8edf5;"><?= e($_SESSION['admin_name'] ?? 'Admin') ?></span>
          <span style="font-size:.68rem;background:rgba(37,99,235,.18);color:#93c5fd;padding:.15rem .55rem;border-radius:4px;font-weight:700;">
            <?= e(strtoupper($_SESSION['admin_role'] ?? 'ADMIN')) ?>
          </span>
        </span>
        <div class="admin-livetime d-none d-md-flex" id="adminLiveTime">
          <i class="bi bi-clock" style="font-size:.8rem;color:#38bdf8;"></i>
          <span id="adminClockDisplay">--:--:--</span>
        </div>
        <button id="themeToggle" class="us-hamburger" title="Toggle theme"><i class="bi bi-sun-fill"></i></button>
        <a href="<?= BASE_URL ?>admin/logout.php" class="us-logout-btn">
          <i class="bi bi-box-arrow-right"></i>
        </a>
      </div>
    </div>
    <div class="admin-page-body">
      <?php foreach(get_flashes() as $flash): ?>
        <div class="alert alert-<?= e($flash['type']==='error'?'danger':$flash['type']) ?> alert-dismissible fade show mb-3">
          <?= e($flash['message']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endforeach; ?>
<style>@keyframes liveBlink{0%,100%{opacity:1}50%{opacity:.35}}</style>

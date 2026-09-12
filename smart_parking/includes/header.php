<?php
require_once __DIR__ . '/auth.php';
$pageTitle  = $pageTitle  ?? APP_NAME;
$activeUser = $activeUser ?? '';

// Detect current page for active nav
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
if ($activeUser === '') {
    $map = ['dashboard'=>'dashboard','book_slot'=>'book','my_bookings'=>'bookings','profile'=>'profile'];
    $activeUser = $map[$currentPage] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — ParkVision</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
<script>
/* Apply saved theme before paint to avoid flash */
(function(){var t=localStorage.getItem('pv_theme')||'dark';document.documentElement.setAttribute('data-bs-theme',t);})();
</script>
</head>
<body>

<div class="user-layout">

  <!-- ── USER SIDEBAR ── -->
  <aside class="user-sidebar" id="userSidebar">

    <div class="us-brand">
      <div class="us-brand-pv">
        <span class="us-bpark">Park</span><span class="us-bvision">Vision</span>
      </div>
      <div class="us-brand-sub">Smart Parking</div>
    </div>

    <?php if(is_user_logged_in()): ?>
    <div class="us-user">
      <div class="us-avatar"><i class="bi bi-person-fill"></i></div>
      <div>
        <div class="us-name"><?= e($_SESSION['user_name'] ?? 'User') ?></div>
        <div class="us-email"><?= e(substr($_SESSION['user_email'] ?? '', 0, 22)) ?><?= strlen($_SESSION['user_email'] ?? '') > 22 ? '…' : '' ?></div>
      </div>
    </div>
    <?php endif; ?>

    <nav class="us-nav">
      <div class="us-section">Navigation</div>
      <a class="us-link <?= $activeUser==='dashboard'?'active':'' ?>" href="<?= BASE_URL ?>user/dashboard.php">
        <i class="bi bi-speedometer2"></i><span>Dashboard</span>
      </a>
      <a class="us-link <?= $activeUser==='book'?'active':'' ?>" href="<?= BASE_URL ?>user/book_slot.php">
        <i class="bi bi-calendar2-plus"></i><span>Book a Slot</span>
      </a>
      <a class="us-link <?= $activeUser==='bookings'?'active':'' ?>" href="<?= BASE_URL ?>user/my_bookings.php">
        <i class="bi bi-journal-bookmark-fill"></i><span>My Bookings</span>
      </a>
      <a class="us-link <?= $activeUser==='profile'?'active':'' ?>" href="<?= BASE_URL ?>user/profile.php">
        <i class="bi bi-person-circle"></i><span>Profile</span>
      </a>

      <div class="us-section" style="margin-top:1rem;">Settings</div>
      <div class="us-link" id="themeToggleUser" style="cursor:pointer;">
        <i class="bi bi-sun-fill"></i><span>Toggle Theme</span>
      </div>
      <a class="us-link" href="<?= BASE_URL ?>user/logout.php" style="color:#f87171 !important;">
        <i class="bi bi-box-arrow-right" style="color:#f87171;"></i><span>Logout</span>
      </a>
    </nav>

    <div class="us-footer">&copy; <?= date('Y') ?> ParkVision</div>
  </aside>

  <!-- ── CONTENT ── -->
  <div class="user-content">

    <!-- Topbar (mobile only shows hamburger, desktop shows page title) -->
    <div class="user-topbar">
      <div class="d-flex align-items-center gap-2">
        <button class="us-hamburger d-lg-none" onclick="document.getElementById('userSidebar').classList.toggle('show')">
          <i class="bi bi-list"></i>
        </button>
        <span class="user-topbar-title"><?= e($pageTitle) ?></span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <div class="us-livetime d-none d-sm-flex" id="userLiveTime">
          <i class="bi bi-clock" style="font-size:.85rem;"></i>
          <span id="userClockDisplay">--:--:--</span>
          <span id="userDateDisplay" style="opacity:.6;font-size:.8rem;"></span>
        </div>
        <button id="themeToggleMobile" class="us-icon-btn d-lg-none" title="Toggle theme"><i class="bi bi-sun-fill"></i></button>
        <a href="<?= BASE_URL ?>user/logout.php" class="us-logout-btn d-lg-none"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>

    <div class="user-body">
    <?php foreach(get_flashes() as $flash): ?>
      <div class="alert alert-<?= e($flash['type']==='error'?'danger':$flash['type']) ?> alert-dismissible fade show mb-3">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endforeach; ?>

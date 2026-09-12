<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$admin = require_admin_login();
$pdo = db();

$editableKeys = ['site_name', 'grace_period_minutes', 'default_hourly_rate', 'currency_symbol', 'timezone'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($editableKeys as $key) {
        if (isset($_POST[$key])) {
            set_setting($key, clean_input($_POST[$key]));
        }
    }
    log_activity('admin', $admin['admin_id'], 'settings_update', 'Updated system settings');
    set_flash('success', 'Settings saved.');
    redirect(BASE_URL . 'admin/settings.php');
}

$settings = [];
foreach ($editableKeys as $key) {
    $settings[$key] = get_setting($key);
}

$pageTitle = 'System Settings';
$activeNav = 'settings';
include __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="card shadow-sm border-0">
      <div class="card-header pv-card-header">System Settings</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label small">Site Name</label>
            <input type="text" name="site_name" class="form-control" value="<?= e($settings['site_name']) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label small">Booking Grace Period (minutes)</label>
            <input type="number" name="grace_period_minutes" class="form-control" value="<?= e($settings['grace_period_minutes']) ?>" min="5" max="120">
            <div class="form-text">How long a confirmed reservation holds the slot before it auto-expires if the vehicle never arrives.</div>
          </div>
          <div class="mb-3">
            <label class="form-label small">Default Hourly Rate</label>
            <input type="number" step="0.01" name="default_hourly_rate" class="form-control" value="<?= e($settings['default_hourly_rate']) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label small">Currency Symbol</label>
            <input type="text" name="currency_symbol" class="form-control" value="<?= e($settings['currency_symbol']) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label small">Timezone</label>
            <input type="text" name="timezone" class="form-control" value="<?= e($settings['timezone']) ?>">
            <div class="form-text">Standard PHP timezone identifier, e.g. Asia/Colombo.</div>
          </div>
          <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
      </div>
    </div>

    <?php if (($admin['role'] ?? '') === 'super_admin'): ?>
    <div class="card shadow-sm border-0 mt-3">
      <div class="card-header pv-card-header">Device API Key</div>
      <div class="card-body">
        <p class="small text-muted mb-1">The unattended entry camera authenticates with this key (set in <code>config/config.php</code>). It cannot be changed from this page for security — edit the file directly on the server and keep it secret.</p>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

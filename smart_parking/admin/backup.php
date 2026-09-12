<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$admin = require_admin_login();
require_super_admin($admin);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {
    csrf_verify();

    if ($_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        set_flash('danger', 'Upload failed. Please try again.');
        redirect(BASE_URL . 'admin/backup.php');
    }

    $sql = file_get_contents($_FILES['backup_file']['tmp_name']);
    if ($sql === false || trim($sql) === '') {
        set_flash('danger', 'The uploaded file is empty or unreadable.');
        redirect(BASE_URL . 'admin/backup.php');
    }

    $pdo = db();
    $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql)));
    $ok = 0; $failed = 0; $errors = [];

    try {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($statements as $stmt) {
            if ($stmt === '' || str_starts_with($stmt, '--') || str_starts_with($stmt, '/*')) {
                continue;
            }
            try {
                $pdo->exec($stmt);
                $ok++;
            } catch (PDOException $e) {
                $failed++;
                $errors[] = $e->getMessage();
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    } catch (Throwable $e) {
        set_flash('danger', 'Restore aborted: ' . $e->getMessage());
        redirect(BASE_URL . 'admin/backup.php');
    }

    log_activity('admin', $admin['admin_id'], 'db_restore', "Restore run: $ok statements OK, $failed failed");

    if ($failed > 0) {
        set_flash('warning', "Restore finished with issues — $ok statement(s) succeeded, $failed failed. First error: " . e($errors[0] ?? ''));
    } else {
        set_flash('success', "Restore completed successfully — $ok statement(s) executed.");
    }
    redirect(BASE_URL . 'admin/backup.php');
}

$pageTitle = 'Backup & Restore';
$activeNav = 'backup';
include __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-8">

    <div class="card shadow-sm border-0 mb-3">
      <div class="card-header pv-card-header">
        <i class="bi bi-cloud-download me-2"></i>Download Backup
      </div>
      <div class="card-body">
        <p class="text-muted small">Downloads a full <code>.sql</code> dump of the database — all tables, structure and data. Keep it somewhere safe (not on this server).</p>
        <a href="<?= BASE_URL ?>admin/backup_download.php?csrf_token=<?= urlencode(csrf_token()) ?>" class="btn btn-primary">
          <i class="bi bi-download me-1"></i> Download Backup Now
        </a>
      </div>
    </div>

    <div class="card shadow-sm border-0" style="border-left:4px solid #f59e0b !important;">
      <div class="card-header pv-card-header">
        <i class="bi bi-cloud-upload me-2"></i>Restore From Backup
      </div>
      <div class="card-body">
        <p class="text-danger small fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-1"></i>Warning: restoring will overwrite existing data for any table in the file. This cannot be undone. Take a fresh backup first.</p>
        <form method="post" enctype="multipart/form-data" onsubmit="return confirm('This will overwrite existing data. Are you sure you want to continue?');">
          <?= csrf_field() ?>
          <div class="mb-3">
            <input type="file" name="backup_file" accept=".sql" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-outline-warning">
            <i class="bi bi-arrow-clockwise me-1"></i> Restore
          </button>
        </form>
      </div>
    </div>

  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
